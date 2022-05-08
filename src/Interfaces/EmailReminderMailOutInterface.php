<?php

namespace SunnySideUp\EmailReminder\Interfaces;

use SilverStripe\Control\HTTPRequest;

use SunnySideUp\EmailReminder\Interfaces\EmailReminderReplacerClassInterface;

interface EmailReminderMailOutInterface
{
    public function setTestOnly(?bool $b = true) : self;

    /**
     * @return null|EmailReminderReplacerClassInterface
     */
    public function getReplacerObject() : ? EmailReminderReplacerClassInterface;

    /**
     * @param mixed $record
     * @param mixed $content
     *
     * @return string
     */
    public function getParsedContent($record, $content) : string;

    /**
     * @param EmailReminderNotificationSchedule $reminder
     * @param DataObject|string                 $recordOrEmail
     * @param bool                              $isTestOnly
     * @param mixed                             $force
     */
    public function send($reminder, $recordOrEmail, ?bool $isTestOnly = false, ?bool $force = false) : bool;
}
