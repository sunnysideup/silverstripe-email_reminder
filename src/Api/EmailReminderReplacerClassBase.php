<?php

namespace SunnySideUp\EmailReminder\Api;

use SilverStripe\Control\Director;
use SilverStripe\ORM\DataObject;

use SilverStripe\View\ViewableData;
use SunnySideUp\EmailReminder\Email\EmailReminderMailer;

use SunnySideUp\EmailReminder\Interfaces\EmailReminderReplacerClassInterface;

class EmailReminderReplacerClassBase extends ViewableData implements EmailReminderReplacerClassInterface
{
    protected $replaceArray = [
        '[PASSWORD_REMINDER_LINK]' => [
            'Title' => 'Password reminder page',
            'Method' => 'PasswordReminderLink',
        ],
        '[LOGIN_LINK]' => [
            'Title' => 'Login Page',
            'Method' => 'LoginLink',
        ],
        '[DAYS]' => [
            'Title' => 'Replaces with the number of days, as set',
            'Method' => 'Days',
        ],
        '[BEFORE_OR_AFTER]' => [
            'Title' => 'Replaces with before or after expiry date, as set',
            'Method' => 'BeforeOrAfter',
        ],
    ];

    /**
     * @return array
     */
    public function getReplaceArray()
    {
        return $this->replaceArray;
    }

    /**
     * @param EmailReminderMailer $reminder
     * @param DataObject $record
     * @param string $str
     *
     * @return string
     */
    public function replace($reminder, $record, string $str): string
    {
        foreach ($this->replaceArray as $searchString => $moreInfoArray) {
            $method = $moreInfoArray['Method'];
            $str = $this->{$method}($reminder, $record, $searchString, $str);
        }
        return $str;
    }

    /**
     * @param bool $asHTML
     *
     * @return array|string
     */
    public function replaceHelpList(?bool $asHTML = false)
    {
        $newArray = [];
        foreach ($this->replaceArray as $searchString => $moreInfoArray) {
            $newArray[$searchString] = $moreInfoArray['Title'];
        }
        if ($asHTML) {
            $html = '
            <ul class="replace-help-list">';
            foreach ($newArray as $searchString => $title) {
                $html .= '
                <li><strong>' . $searchString . ':</strong> <span>' . $title . '</span></li>';
            }
            $html .= '
            </ul>';
            return $html;
        }
        return $newArray;
    }

    /**
     * @param EmailReminderMailer $reminder
     * @param DataObject $record
     * @param string $searchString
     * @param string $str
     *
     * @return string
     */
    protected function PasswordReminderLink($reminder, $record, string $searchString, string $str): string
    {
        $replace = Director::absoluteURL('Security/lostpassword');
        return str_replace($searchString, $replace, $str);
    }

    /**
     * @param EmailReminderMailer $reminder
     * @param DataObject $record
     * @param string $searchString
     * @param string $str
     *
     * @return string
     */
    protected function LoginLink($reminder, $record, string $searchString, string $str): string
    {
        $replace = Director::absoluteURL('Security/login');
        return str_replace($searchString, $replace, $str);
    }

    /**
     * @param EmailReminderMailer $reminder
     * @param DataObject $record
     * @param string $searchString
     * @param string $str
     *
     * @return string
     */
    protected function Days($reminder, $record, string $searchString, string $str): string
    {
        $replace = $reminder->Days;
        return str_replace($searchString, $replace, $str);
    }

    /**
     * @param EmailReminderMailer $reminder
     * @param DataObject $record
     * @param string $searchString
     * @param string $str
     *
     * @return string
     */
    protected function BeforeOrAfter($reminder, $record, string $searchString, string $str): string
    {
        $replace = $reminder->BeforeAfter;
        return str_replace($searchString, $replace, $str);
    }
}
