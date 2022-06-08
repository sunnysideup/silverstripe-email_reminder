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
use SunnySideUp\EmailReminder\Api\EmailReminderMailOut;
use SunnySideUp\EmailReminder\Api\EmailReminderEmogrifier;
use SunnySideUp\EmailReminder\Api\EmailReminderReplacerClassBase;
use SunnySideUp\EmailReminder\Email\EmailReminderMailer;
use SunnySideUp\EmailReminder\Interfaces\EmailReminderMailOutInterface;
use SunnySideUp\EmailReminder\Interfaces\EmailReminderReplacerClassInterface;
use SunnySideUp\EmailReminder\Model\EmailReminderEmailRecord;
use SunnySideUp\EmailReminder\Model\EmailReminderNotificationSchedule;

class EmailReminderDailyMailOut extends BuildTask
{
    protected $verbose = false;

    protected $testOnly = false;

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
        $this->runAll();
    }

    protected function runAll()
    {
        $reminders = EmailReminderNotificationSchedule::get();
        foreach ($reminders as $reminder) {
            if (! $reminder->hasValidFields()) {
                continue; // skip if task is not valid
            }
            if (! $reminder->IsImmediate()) {
                continue; // skip if they are sent on the fly...
            }
            if ($reminder->Disable) {
                continue; // skip if task is disable
            }

            // Use StartsWith to match Date and DateTime fields
            if ($this->testOnly) {
                if ($reminder->SendTestTo) {
                    $emails = explode(',', $reminder->SendTestTo);
                    foreach ($emails as $email) {
                        $reminder->sendOne($email, $isTestOnly = true);
                    }
                }
            } else {
                $limit = Config::inst()->get(EmailReminderDailyMailOut::class, 'daily_limit');

                $records = $reminder->CurrentRecords()->limit($limit);
                if ($records) {
                    foreach ($records as $record) {
                        $reminder->sendOne($record, $isTestOnly = false);
                    }
                }
            }
        }
    }

}
