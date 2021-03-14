<?php

namespace SunnySideUp\EmailReminder\Interfaces;

use SilverStripe\ORM\DataObject;

use SunnySideUp\EmailReminder\Model\EmailReminderNotificationSchedule;

interface EmailReminderReplacerClassInterface
{
    /**
     * replaces all instances of certain
     * strings in the string and returns the string
     *
     * @param EmailReminderNotificationSchedule   $reminder
     * @param DataObject                          $record
     * @param string                              $str
     *
     * @return string
     */
    public function replace($reminder, $record, string $str): string;

    /**
     * provides and array of replacements like this:
     *
     *     [string to replace] => 'description of what it does'
     * @param bool $asHTML
     *
     * @return array
     */
    public function replaceHelpList(?bool $asHTML = false): array;
}
