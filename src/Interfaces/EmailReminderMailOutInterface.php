<?php

namespace SunnySideUp\EmailReminder\Interfaces;

interface EmailReminderMailOutInterface
{
    public function setTestOnly(?bool $b = true): self;

    public function getReplacerObject(): ?EmailReminderReplacerClassInterface;

    /**
     * @param mixed $record
     * @param mixed $content
     */
    public function getParsedContent($record, $content): string;

    /**
     * @param EmailReminderNotificationSchedule $reminder
     * @param DataObject|string                 $recordOrEmail
     * @param bool                              $isTestOnly
     * @param mixed                             $force
     */
    public function send($reminder, $recordOrEmail, ?bool $isTestOnly = false, ?bool $force = false): bool;
}
