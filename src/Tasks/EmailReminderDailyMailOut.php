<?php

namespace SunnySideUp\EmailReminder\Tasks;

use SilverStripe\Control\Email\Email;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Parsers\ShortcodeParser;
use SilverStripe\View\SSViewer;
use SunnySideUp\EmailReminder\Api\EmailReminderReplacerClassBase;
use SunnySideUp\EmailReminder\Email\EmailReminderMailer;
use SunnySideUp\EmailReminder\Interfaces\EmailReminderMailOutInterface;
use SunnySideUp\EmailReminder\Interfaces\EmailReminderReplacerClassInterface;
use SunnySideUp\EmailReminder\Model\EmailReminderEmailRecord;
use SunnySideUp\EmailReminder\Model\EmailReminderNotificationSchedule;

class EmailReminderDailyMailOut extends BuildTask implements EmailReminderMailOutInterface
{
    protected $verbose = false;

    protected $testOnly = false;

    /**
     * The object that replaces tags in the subject and content.
     * @var EmailReinder_ReplacerClassInterface
     */
    protected $replacerObject;

    /**
     * @var int
     */
    private static $limit = 20;

    /**
     * @var string
     */
    private static $replacer_class = EmailReminderReplacerClassBase::class;

    public function setVerbose($b)
    {
        $this->verbose = $b;
    }

    public function setTestOnly($b)
    {
        $this->testOnly = $b;
    }

    /**
     * @todo: https://docs.silverstripe.org/en/3.1/developer_guides/extending/injector/ implement
     * for email class to be used...
     *
     * expire date = 08-09
     * days before 7
     * min: current date + 7 - grace days
     * min: current date + 7
     *
     * expire date = 08-09
     * days after 7
     * min: current date - 7
     * max current date - 7 - grace days
     *
     * @param HTTPRequest $request
     */
    public function run($request)
    {
        $this->startSending();
        $this->runAll();
    }

    /**
     * @param  EmailReminderNotificationSchedule  $reminder
     * @param  string|DataObject  $recordOrEmail
     * @param  bool $isTestOnly
     */
    public function runOne($reminder, $recordOrEmail, $isTestOnly = false, $force = false)
    {
        $this->startSending();
        $this->sendEmail($reminder, $recordOrEmail, $isTestOnly, $force);
    }

    /**
     * @return EmailReminderReplacerClassInterface|null
     */
    public function getReplacerObject()
    {
        if (! $this->replacerObject) {
            $replacerClass = Config::inst()->get(EmailReminderDailyMailOut::class, 'replacer_class');
            if ($replacerClass && class_exists($replacerClass)) {
                $interfaces = class_implements($replacerClass);
                if ($interfaces && in_array(EmailReminderReplacerClassInterface::class, $interfaces, true)) {
                    $this->replacerObject = Injector::inst()->get($replacerClass);
                }
            }
        }
        return $this->replacerObject;
    }

    /**
     * @return string
     */
    public function getParsedContent($record, $content)
    {
        return ShortcodeParser::get_active()
            ->parse(
                $record->RenderWith(
                    SSViewer::fromString($content)
                )
            );
    }

    protected function startSending()
    {
        //CRUCIAL !
        Injector::inst()->registerService(new EmailReminderMailer(), 'Mailer');
    }

    protected function runAll()
    {
        $reminders = EmailReminderNotificationSchedule::get();

        foreach ($reminders as $reminder) {
            if (! $reminder->hasValidFields()) {
                continue; // skip if task is not valid
            }
            if ($reminder->Disable) {
                continue; // skip if task is disable
            }

            // Use StartsWith to match Date and DateTime fields
            if ($this->testOnly) {
                if ($reminder->SendTestTo) {
                    $emails = explode(',', $reminder->SendTestTo);
                    foreach ($emails as $email) {
                        $this->sendEmail($reminder, $email, $isTestOnly = true);
                    }
                }
            } else {
                $limit = Config::inst()->get(EmailReminderDailyMailOut::class, 'daily_limit');

                $records = $reminder->CurrentRecords();
                $records = $records->limit($limit);
                if ($records) {
                    foreach ($records as $record) {
                        $this->sendEmail($reminder, $record, $isTestOnly = false);
                    }
                }
            }
        }
    }

    protected function sendEmail($reminder, $recordOrEmail, $isTestOnly, $force = false)
    {
        $filter = [
            'EmailReminderNotificationScheduleID' => $reminder->ID,
        ];
        if ($recordOrEmail instanceof DataObject) {
            $email_field = $reminder->EmailField;
            $email = $recordOrEmail->{$email_field};
            $record = $recordOrEmail;
            $filter['ExternalRecordClassName'] = $recordOrEmail->ClassName;
            $filter['ExternalRecordID'] = $recordOrEmail->ID;
        } else {
            $email = strtolower(trim($recordOrEmail));
            $record = Injector::inst()->get($reminder->DataObject);
        }
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
                $log = EmailReminderEmailRecord::create($filter);

                $subject = $reminder->EmailSubject;
                $email_content = $reminder->Content;
                if (($replacerObject = $this->getReplacerObject()) !== null) {
                    $email_content = $replacerObject->replace($reminder, $record, $email_content);
                    $subject = $replacerObject->replace($reminder, $record, $subject);
                }
                $email_content = $this->getParsedContent($record, $email_content);

                /* Parse HTML like a template, and translate any internal links */
                $data = ArrayData::create([
                    'Content' => $email_content,
                ]);

                // $email_body = $record->renderWith(SSViewer::fromString($reminder->Content));
                // echo $record->renderWith('SunnySideUp/EmailReminder/Email/EmailReminderStandardTemplate');//$email_body;
                $email = new Email(
                    $reminder->EmailFrom,
                    $email,
                    $subject
                );

                $email->setHTMLTemplate('SunnySideUp/EmailReminder/Email/EmailReminderStandardTemplate');

                $email->setData($data);

                // $email->send();
                $log->IsTestOnly = $isTestOnly;
                $log->Result = $email->send();
                $log->EmailReminderNotificationScheduleID = $reminder->ID;
                $log->EmailContent = $email->body;
                $log->write();
            }
        }
        return false;
    }
}
