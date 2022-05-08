<?php

namespace SunnySideUp\EmailReminder\Interfaces;

use SilverStripe\Control\HTTPRequest;

use SunnySideUp\EmailReminder\Interfaces\EmailReminderReplacerClassInterface;

interface EmailReminderMailOutInterface
{
    public function setVerbose($b);

    public function setTestOnly($b);

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
}
