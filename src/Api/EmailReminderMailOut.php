<?php

namespace SunnySideUp\EmailReminder\Api;

use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Parsers\ShortcodeParser;
use SilverStripe\View\SSViewer;
use SilverStripe\View\ViewableData;
use SunnySideUp\EmailReminder\Interfaces\EmailReminderMailOutInterface;
use SunnySideUp\EmailReminder\Interfaces\EmailReminderReplacerClassInterface;
use SunnySideUp\EmailReminder\Model\EmailReminderEmailRecord;
use SunnySideUp\EmailReminder\Model\EmailReminderNotificationSchedule;
use Exception;

class EmailReminderMailOut extends ViewableData implements EmailReminderMailOutInterface
{
    protected $testOnly = false;

    /**
     * The object that replaces tags in the subject and content.
     *
     * @var EmailReminderReplacerClassInterface
     */
    protected $replacerObject;

    /**
     * @var int
     */
    private static $daily_limit = 200;

    /**
     * @var int
     */
    private static float $grace_days_for_immediate_emails = 0.000694444; // 24 / 60 / 30 => 2 minutes;

    /**
     * @var string
     */
    private static $replacer_class = EmailReminderReplacerClassBase::class;

    /**
     * template used for emails.
     *
     * @var string
     */
    private static $template = 'Sunnysideup/EmailReminder/Email/EmailReminderStandardTemplate';

    /**
     * emails always to be included in mail out, even if sent already...
     *
     * @var array
     */
    private static $always_send_to = [];

    public function setTestOnly(?bool $b = true): self
    {
        $this->testOnly = $b;

        return $this;
    }

    /**
     * returns true on success.
     *
     * @param EmailReminderNotificationSchedule $reminder
     * @param DataObject|string                 $recordOrEmail
     * @param bool                              $isTestOnly
     * @param bool                              $force
     */
    public function send($reminder, $recordOrEmail, ?bool $isTestOnly = false, ?bool $force = false): bool
    {
        return $this->sendEmail($reminder, $recordOrEmail, $isTestOnly, $force);
    }

    public function getReplacerObject(): ?EmailReminderReplacerClassInterface
    {
        if (! $this->replacerObject) {
            $this->replacerObject = null;
            $replacerClass = Config::inst()->get(EmailReminderMailOut::class, 'replacer_class');
            if ($replacerClass && class_exists($replacerClass)) {
                $this->replacerObject = Injector::inst()->get($replacerClass);
                if (! $this->replacerObject instanceof EmailReminderReplacerClassInterface) {
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
     */
    public function getParsedContent($record, $content): string
    {
        return ShortcodeParser::get_active()
            ->parse(
                $record->RenderWith(
                    SSViewer::fromString($content)
                )
            )
        ;
    }

    /**
     * returns true on success.
     *
     * @param EmailReminderNotificationSchedule $reminder
     * @param DataObject|string                 $recordOrEmail
     * @param bool                              $isTestOnly
     * @param bool                              $force
     */
    protected function sendEmail($reminder, $recordOrEmail, ?bool $isTestOnly = false, ?bool $force = false): bool
    {
        // always send test
        if ($isTestOnly) {
            $force = true;
        }

        $filter = [
            'EmailReminderNotificationScheduleID' => $reminder->ID,
            'IsTestOnly' => $isTestOnly,
        ];
        if ($recordOrEmail instanceof DataObject) {
            $emailField = $reminder->EmailField;
            $email = $recordOrEmail->{$emailField};
            $record = $recordOrEmail;
        } else {
            $email = (string) $recordOrEmail;
            $record = Injector::inst()->get($reminder->DataObject);
        }

        $email = strtolower(trim($email));
        if (Email::is_valid_address($email)) {
            $filter['ExternalRecordClassName'] = $record->ClassName;
            $filter['ExternalRecordID'] = $record->ID;
            $filter['EmailTo'] = $email;
            $alwaysSendTo = $this->Config()->get('always_send_to');
            // always send to admin
            if (
                $email === Config::inst()->get(Email::class, 'admin_email') ||
                in_array($email, $alwaysSendTo, true)
            ) {
                $force = true;
            }

            $send = true;
            if (true !== $force) {
                $logs = EmailReminderEmailRecord::get()->filter($filter);
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
     * @param EmailReminderNotificationSchedule $reminder
     * @param DataObject                        $record
     * @param array                             $filter
     * @param bool                              $isTestOnly
     */
    protected function sendInner(string $email, $reminder, $record, array $filter, ?bool $isTestOnly = false): bool
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
        // $Cc = '';
        // $Bcc = '';
        $otherEmails = [];
        $carbonCopyFields = [$reminder->CarbonCopyMethod => 'setCc', $reminder->BlindCarbonCopyMethod => 'setBcc'];
        foreach ($carbonCopyFields as $recordMethod => $emailMethod) {
            if ($recordMethod) {
                $array = (array) $record->$recordMethod();
                if (! empty($array)) {
                    $email->$emailMethod($array);
                    $otherEmails[$emailMethod] = $array;
                }
            }
        }

        $email->setHTMLTemplate(Config::inst()->get(EmailReminderMailOut::class, 'template'));

        $email->setData($data);

        // send email
        try {
            $email->send();
            $outcome = true;
        } catch (Exception $e) {
            $outcome = false;
        }

        // create log - using filter as starting point
        $log = EmailReminderEmailRecord::create($filter);
        $log->HasTried = true;
        $log->Result = (bool) $outcome;
        $log->Subject = $subject;
        $log->EmailContent = $email->getBody()?->bodyToString();
        $log->EmailCc = implode(', ', $otherEmails['setCc'] ?? []);
        $log->EmailBcc = implode(', ', $otherEmails['setBcc'] ?? []);
        $log->write();

        return (bool) $log->Result;
    }
}
