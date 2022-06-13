<?php

namespace SunnySideUp\EmailReminder\Api;

use SilverStripe\Control\Email\Email;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Parsers\ShortcodeParser;

use SilverStripe\View\ViewableData;
use SilverStripe\View\SSViewer;
use SunnySideUp\EmailReminder\Api\EmailReminderEmogrifier;
use SunnySideUp\EmailReminder\Api\EmailReminderReplacerClassBase;
use SunnySideUp\EmailReminder\Email\EmailReminderMailer;
use SunnySideUp\EmailReminder\Interfaces\EmailReminderMailOutInterface;
use SunnySideUp\EmailReminder\Interfaces\EmailReminderReplacerClassInterface;
use SunnySideUp\EmailReminder\Model\EmailReminderEmailRecord;
use SunnySideUp\EmailReminder\Model\EmailReminderNotificationSchedule;

class EmailReminderMailOut extends ViewableData implements EmailReminderMailOutInterface
{

    /**
     * @var int
     */
    private static $limit = 200;

    /**
     * @var string
     */
    private static $replacer_class = EmailReminderReplacerClassBase::class;

    /**
     * template used for emails
     * @var string
     */
    private static $template = 'SunnySideUp/EmailReminder/Email/EmailReminderStandardTemplate';

    protected $testOnly = false;

    /**
     * The object that replaces tags in the subject and content.
     *
     * @var EmailReminderReplacerClassInterface
     */
    protected $replacerObject;

    public function setTestOnly(?bool $b = true) : self
    {
        $this->testOnly = $b;
        return $this;
    }

    /**
     * @param EmailReminderNotificationSchedule $reminder
     * @param DataObject|string                 $recordOrEmail
     * @param bool                              $isTestOnly
     * @param mixed                             $force
     */
    public function send($reminder, $recordOrEmail, ?bool $isTestOnly = false, ?bool $force = false) : bool
    {
        return $this->sendEmail($reminder, $recordOrEmail, $isTestOnly, $force);
    }

    /**
     * @return null|EmailReminderReplacerClassInterface
     */
    public function getReplacerObject(): ?EmailReminderReplacerClassInterface
    {
        if (! $this->replacerObject) {
            $this->replacerObject = null;
            $replacerClass = Config::inst()->get(EmailReminderMailOut::class, 'replacer_class');
            if ($replacerClass && class_exists($replacerClass)) {
                $this->replacerObject = Injector::inst()->get($replacerClass);
                if(!$this->replacerObject instanceof EmailReminderReplacerClassInterface) {
                    $this->replacerObject = null;
                    user_error('Replacer object needs to be a EmailReminderReplacerClassInterface');
                }
            }
        }

        return $this->replacerObject;
    }

    /**
     * @param mixed $record
     * @param mixed $content
     *
     * @return string
     */
    public function getParsedContent($record, $content) : string
    {
        return ShortcodeParser::get_active()
            ->parse(
                $record->RenderWith(
                    SSViewer::fromString($content)
                )
            )
        ;
    }

    protected function sendEmail($reminder, $recordOrEmail, ?bool $isTestOnly = false, ?bool $force = false) : bool
    {
        $filter = [
            'EmailReminderNotificationScheduleID' => $reminder->ID,
        ];
        if ($recordOrEmail instanceof DataObject) {
            $emailField = $reminder->EmailField;
            $email = $recordOrEmail->{$emailField};
            $record = $recordOrEmail;
            $filter['ExternalRecordClassName'] = $recordOrEmail->ClassName;
            $filter['ExternalRecordID'] = $recordOrEmail->ID;
        } else {
            $email = (string) $recordOrEmail;
            $record = Injector::inst()->get($reminder->DataObject);
        }
        $email = strtolower(trim($email));
        $filter['EmailTo'] = $email;
        if (Email::is_valid_address($email)) {
            $send = true;
            if (! $force) {
                $logs = EmailReminderEmailRecord::get()->filter($filter);
                $send = true;
                foreach ($logs as $log) {
                    if (! $log->canSendAgain()) {
                        $send = false;

                        break;
                    }
                }
            }
            if ($send) {
                return (bool) $this->sendInner($email, $reminder, $record, $filter, $isTestOnly);
            }
        }

        return false;
    }

    /**
     *
     * @param  string                            $email
     * @param  EmailReminderNotificationSchedule $reminder
     * @param  DataObject $record
     * @param  array  $filter
     * @param  bool  $isTestOnly
     *
     * @return bool
     */
    protected function sendInner(string $email, $reminder, $record, array $filter, ?bool $isTestOnly = false) : bool
    {
        // build email
        $subject = $reminder->EmailSubject;
        $emailContent = $reminder->Content;
        $replacerObject = $this->getReplacerObject();
        if (null !== $replacerObject) {
            $emailContent = $replacerObject->replace($reminder, $record, $emailContent);
            $subject = $replacerObject->replace($reminder, $record, $subject);
        }
        $emailContent = $this->getParsedContent($record, $emailContent);

        //emogrify the content
        $emailContent = EmailReminderEmogrifier::emogrify($emailContent);

        // Parse HTML like a template, and translate any internal links
        $data = ArrayData::create([
            'Content' => $emailContent,
        ]);

        // $email_body = $record->renderWith(SSViewer::fromString($reminder->Content));
        // echo $record->renderWith('SunnySideUp/EmailReminder/Email/EmailReminderStandardTemplate');//$email_body;
        $email = new Email(
            $reminder->EmailFrom,
            $email,
            $subject
        );

        $email->setHTMLTemplate(Config::inst()->get(EmailReminderMailOut::class, 'template'));

        $email->setData($data);

        // send email
        $log = EmailReminderEmailRecord::create($filter);
        // $email->send();
        $log->IsTestOnly = $isTestOnly;
        $outcome = $email->send();
        $log->HasTried = true;
        $log->write();
        $log->Result = (bool) $outcome;
        $log->EmailReminderNotificationScheduleID = $reminder->ID;
        $log->Subject = $subject;
        $log->EmailContent = $email->body;
        $log->write();
        return (bool) $log->Result;
    }
}
