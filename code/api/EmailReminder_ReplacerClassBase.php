<?php

class EmailReminder_ReplacerClassBase extends Object implements EmailReminder_ReplacerClassInterface
{
    private $replaceArray = array(
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
            'Title' => 'Replaces with before or after experiry date, as set',
            'Method' => 'BeforeOrAfter'
        )
    );

    public function getReplaceArray()
    {
        return $this->replaceArray;
    }

    public function replace($reminder, $record, $str)
    {
        $newArray = array();
        foreach ($this->replaceArray as $searchString => $moreInfoArray) {
            $method = $moreInfoArray['Method'];
            $str = $this->$method($reminder, $record, $searchString, $str);
        }
        return $str;
    }

    public function replaceHelpList($asHTML = false)
    {
        $newArray = array();
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

    protected function PasswordReminderLink($reminder, $record, $searchString, $str)
    {
        $replace = Director::absoluteURL('Security/lostpassword');
        $str = str_replace($searchString, $replace, $str);
        return $str;
    }

    protected function LoginLink($reminder, $record, $searchString, $str)
    {
        $replace = Director::absoluteURL('Security/login');
        $str = str_replace($searchString, $replace, $str);
        return $str;
    }

    protected function Days($reminder, $record, $searchString, $str)
    {
        $replace = $reminder->Days;
        $str = str_replace($searchString, $replace, $str);
        return $str;
    }

    protected function BeforeOrAfter($reminder, $record, $searchString, $str)
    {
        $replace = $reminder->BeforeAfter;
        $str = str_replace($searchString, $replace, $str);
        return $str;
    }
}
