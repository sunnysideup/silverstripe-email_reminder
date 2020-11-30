<?php

namespace SunnySideUp\EmailReminder\Api;




use SilverStripe\Control\Director;
use SilverStripe\View\ViewableData;
use SunnySideUp\EmailReminder\Interfaces\EmailReminder_ReplacerClassInterface;




/**
  * ### @@@@ START REPLACEMENT @@@@ ###
  * WHY: automated upgrade
  * OLD:  extends Object (ignore case)
  * NEW:  extends ViewableData (COMPLEX)
  * EXP: This used to extend Object, but object does not exist anymore. You can also manually add use Extensible, use Injectable, and use Configurable
  * ### @@@@ STOP REPLACEMENT @@@@ ###
  */
class EmailReminder_ReplacerClassBase extends ViewableData implements EmailReminder_ReplacerClassInterface
{
    protected $replaceArray = array(
        '[PASSWORD_REMINDER_LINK]' => array(
            'Title' => 'Password reminder page',
            'Method' => 'PasswordReminderLink'
        ),
        '[LOGIN_LINK]' => array(
            'Title' => 'Login Page',
            'Method' => 'LoginLink'
        ),
        '[DAYS]' => array(
            'Title' => 'Replaces with the number of days, as set',
            'Method' => 'Days'
        ),
        '[BEFORE_OR_AFTER]' => array(
            'Title' => 'Replaces with before or after expiry date, as set',
            'Method' => 'BeforeOrAfter'
        )
    );

    /**
     *
     * @return array
     */
    public function getReplaceArray()
    {
        return $this->replaceArray;
    }

    /**
     *
     * @param EmailReminder $reminder
     * @param DataObject $record
     * @param string $str
     *
     * @return string
     */
    public function replace($reminder, $record, $str)
    {
        $newArray = [];
        foreach ($this->replaceArray as $searchString => $moreInfoArray) {
            $method = $moreInfoArray['Method'];
            $str = $this->$method($reminder, $record, $searchString, $str);
        }
        return $str;
    }


    /**
     *
     * @param bool $asHTML
     *
     * @return array
     */
    public function replaceHelpList($asHTML = false)
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
                <li><strong>'.$searchString.':</strong> <span>'.$title.'</span></li>';
            }
            $html .= '
            </ul>';
            return $html;
        }
        return $newArray;
    }

    /**
     *
     * @param EmailReminder $reminder
     * @param DataObject $record
     * @param string $searchString
     * @param string $str
     *
     * @return string
     */
    protected function PasswordReminderLink($reminder, $record, $searchString, $str)
    {
        $replace = Director::absoluteURL('Security/lostpassword');
        $str = str_replace($searchString, $replace, $str);
        return $str;
    }

    /**
     *
     * @param EmailReminder $reminder
     * @param DataObject $record
     * @param string $searchString
     * @param string $str
     *
     * @return string
     */
    protected function LoginLink($reminder, $record, $searchString, $str)
    {
        $replace = Director::absoluteURL('Security/login');
        $str = str_replace($searchString, $replace, $str);
        return $str;
    }

    /**
     *
     * @param EmailReminder $reminder
     * @param DataObject $record
     * @param string $searchString
     * @param string $str
     *
     * @return string
     */
    protected function Days($reminder, $record, $searchString, $str)
    {
        $replace = $reminder->Days;
        $str = str_replace($searchString, $replace, $str);
        return $str;
    }

    /**
     *
     * @param EmailReminder $reminder
     * @param DataObject $record
     * @param string $searchString
     * @param string $str
     *
     * @return string
     */
    protected function BeforeOrAfter($reminder, $record, $searchString, $str)
    {
        $replace = $reminder->BeforeAfter;
        $str = str_replace($searchString, $replace, $str);
        return $str;
    }
}

