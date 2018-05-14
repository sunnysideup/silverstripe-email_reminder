<?php


class EmailReminder_DailyMailOut extends BuildTask implements EmailReminder_MailOutInterface
{


    /**
     * @var int
     */
    private static $limit = 20;

    /**
     * @var string
     */
    private static $replacer_class = 'EmailReminder_ReplacerClassBase';


    protected $verbose = false;

    protected $testOnly = false;

    /**
     * The object that replaces tags in the subject and content.
     * @var EmailReinder_ReplacerClassInterface
     */
    protected $replacerObject = null;


    public function setVerbose($b)
    {
        $this->verbose = $b;
    }

    public function setTestOnly($b)
    {
        $this->testOnly = $b;
    }

    /**
     *
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
     * @param  SS_Request $request
     */
    public function run($request)
    {
        $this->startSending();
        $this->runAll();
    }

    protected function startSending()
    {
        //CRUCIAL !
        //
        Email::set_mailer(new EmailReminder_Mailer());
    }


    /**
     *
     * @param  EmailReminder_NotificationSchedule  $reminder
     * @param  string|DataObject  $recordOrEmail
     * @param  bool $isTestOnly
     */
    public function runOne($reminder, $recordOrEmail, $isTestOnly = false, $force = false)
    {
        $this->startSending();
        $this->sendEmail($reminder, $recordOrEmail, $isTestOnly, $force);
    }

    protected function runAll()
    {
        $reminders = EmailReminder_NotificationSchedule::get();
        foreach ($reminders as $reminder) {
            if (! $reminder->hasValidFields()) {
                continue; // skip if task is not valid
            }
            if ($reminder->Disabled) {
                continue; // skip if task is disable
            }

            // Use StartsWith to match Date and DateTime fields
            if ($this->testOnly) {
                if ($reminder->SendTestTo) {
                    $emails = explode(',', $reminder->SendTestTo);
                    foreach ($emails as $key => $email) {
                        $this->sendEmail($reminder, $email, $isTestOnly = true);
                    }
                }
            } else {
                $limit = Config::inst()->get('EmailReminder_DailyMailOut', 'daily_limit');
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
        $filter = array(
            'EmailReminder_NotificationScheduleID' => $reminder->ID,
        );
        if ($recordOrEmail instanceof DataObject) {
            $email_field = $reminder->EmailField;
            $email = $recordOrEmail->$email_field;
            $record = $recordOrEmail;
            $filter['ExternalRecordClassName'] = $recordOrEmail->ClassName;
            $filter['ExternalRecordID'] = $recordOrEmail->ID;
        } else {
            $email = strtolower(trim($recordOrEmail));
            $record = Injector::inst()->get($reminder->DataObject);
        }
        $filter['EmailTo'] = $email;
        if (Email::validEmailAddress($email)) {
            $send = true;
            if (! $force) {
                $logs = EmailReminder_EmailRecord::get()->filter($filter);
                $send = true;
                foreach ($logs as $log) {
                    if (! $log->canSendAgain()) {
                        $send = false;
                        break;
                    }
                }
            }
            if ($send) {
                $log = EmailReminder_EmailRecord::create($filter);

                $subject = $reminder->EmailSubject;
                $email_content = $reminder->Content;
                if ($replacerObject = $this->getReplacerObject()) {
                    $email_content = $replacerObject->replace($reminder, $record, $email_content);
                    $subject = $replacerObject->replace($reminder, $record, $subject);
                }
                $email_content = $this->getParsedContent($record, $email_content);

                /* Parse HTML like a template, and translate any internal links */
                $data = ArrayData::create(array(
                    'Content' => $email_content
                ));

                // $email_body = $record->renderWith(SSViewer::fromString($reminder->Content));
                // echo $record->renderWith('Email_Reminder_Standard_Template');//$email_body;
                $email = new Email(
                    $reminder->EmailFrom,
                    $email,
                    $subject
                );

                $email->setTemplate('Email_Reminder_Standard_Template');

                $email->populateTemplate($data);

                // $email->send();
                $log->IsTestOnly = $isTestOnly;
                $log->Result = $email->send();
                $log->EmailReminder_NotificationScheduleID = $reminder->ID;
                $log->EmailContent = $email->body;
                $log->write();
            }
        }
        return false;
    }


    /**
     * @return EmailReminder_ReplacerClassInterface | null
     */
    public function getReplacerObject()
    {
        if (! $this->replacerObject) {
            $replacerClass = Config::inst()->get("EmailReminder_DailyMailOut", "replacer_class");
            if ($replacerClass && class_exists($replacerClass)) {
                $interfaces = class_implements($replacerClass);
                if ($interfaces && in_array('EmailReminder_ReplacerClassInterface', $interfaces)) {
                    $this->replacerObject = Injector::inst()->get($replacerClass);
                }
            }
        }
        return $this->replacerObject;
    }


    /**
     *
     * @return string
     */
    public function getParsedContent($record, $content)
    {
        return ShortcodeParser::get_active()
            ->parse(
                $record->renderWith(
                    SSViewer::fromString($content)
                )
            );
    }
}
