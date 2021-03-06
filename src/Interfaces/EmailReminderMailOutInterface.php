<?php

namespace SunnySideUp\EmailReminder\Interfaces;

use SilverStripe\Control\HTTPRequest;

interface EmailReminderMailOutInterface
{
    public function setVerbose($b);

    public function setTestOnly($b);

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
    public function run($request);

    /**
     * @return null|EmailReminderReplacerClassInterface
     */
    public function getReplacerObject();

    /**
     * @param mixed $record
     * @param mixed $content
     *
     * @return string
     */
    public function getParsedContent($record, $content);
}
