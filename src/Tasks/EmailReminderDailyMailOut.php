<?php

namespace SunnySideUp\EmailReminder\Tasks;

use SilverStripe\Control\Email\Email;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use SunnySideUp\EmailReminder\Model\EmailReminderNotificationSchedule;

class EmailReminderDailyMailOut extends BuildTask
{
    protected $verbose = true;

    protected $testOnly = false;

    private static $segment = 'email-reminder-daily-mail-out';

    protected $title = 'Email Reminder Daily Mail Out';

    protected $description = 'Send out daily email reminders';


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
            if ($this->verbose) {
                DB::alteration_message('Sending reminder for ' . $reminder->Title, 'created');
            }
            if (! $reminder->hasValidFields()) {
                if ($this->verbose) {
                    DB::alteration_message('... Skip, does not have valid fields ' . $reminder->Title, 'deleted');
                }
                continue; // skip if task is not valid
            }

            if ($reminder->IsImmediate()) {
                if ($this->verbose) {
                    DB::alteration_message('... Is immediate send, no need to send', 'edited');
                }
                continue; // skip if they are sent on the fly...
            }

            if ($reminder->Disable) {
                if ($this->verbose) {
                    DB::alteration_message('... Is disabled for daily, no need to send', 'edited');
                }
                continue; // skip if task is disable
            }

            // Use StartsWith to match Date and DateTime fields
            if ($this->testOnly) {
                if ($this->verbose) {
                    DB::alteration_message('... Sending test email', 'edited');
                }
                if ($reminder->SendTestTo) {
                    $emails = explode(',', $reminder->SendTestTo);
                    foreach ($emails as $email) {
                        $reminder->sendOne($email, $isTestOnly = true);
                    }
                }
            } else {
                $limit = Config::inst()->get(EmailReminderDailyMailOut::class, 'daily_limit');
                $records = $reminder->CurrentRecords()->limit($limit);
                if ($this->verbose) {
                    DB::alteration_message('... Sending our real emails' . $records->count(), 'edited');
                }
                if ($records) {
                    foreach ($records as $record) {
                        $reminder->sendOne($record, $isTestOnly = false);
                    }
                }
            }
        }
    }
}
