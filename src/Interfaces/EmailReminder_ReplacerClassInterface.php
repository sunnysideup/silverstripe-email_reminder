<?php

namespace SunnySideUp\EmailReminder\Interfaces;





interface EmailReminder_ReplacerClassInterface
{

    /**
     * replaces all instances of certain
     * strings in the string and returns the string
     *
     * @param EmailReminder_NotificationSchedule  $reminder
     * @param DataObject                          $record
     * @param string                              $str
     *
     * @return string
     */
    public function replace($reminder, $record, $str);

    /**
     * provides and array of replacements like this:
     *
     *     [string to replace] => 'description of what it does'
     * @param bool $asHTML
     *
     * @return array
     */
    public function replaceHelpList($asHTML = false);
}
