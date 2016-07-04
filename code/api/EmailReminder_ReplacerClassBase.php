<?php

class EmailReminder_ReplacerClassBase extends Object implements EmailReminder_ReplacerClassInterface
{

    private $replaceArray = array(
        '[PASSWORD_REMINDER_LINK]' = array(
            'Title' => 'Password reminder page'
            'Method' => 'PasswordReminderLink'
        ),
        '[LOGIN_LINK]' = array(
            'Title' => 'Login Page'
            'Method' => 'LoginLink'
        ),
        
    );

    public function getReplaceArray()
    {
        return $this->replaceArray;
    }
    
    function replace($str)
    {
        $newArray = array();
        foreach($this->replaceArray as $searchString => $moreInfoArray) {
            $method = $moreInfoArray['Method'];
            $str = $this->$method($searchString, $str);
        }
        return $str;
        
    }

    function replaceHelpList()
    {
        $newArray = array();
        foreach($this->replaceArray as $searchString => $moreInfoArray) {
            $newArray[$searchstring] = $moreInfoArray['Title'];
        }
        return $newArray;
    }

    protected function PasswordReminderLink($searchString, $str)
    {
        $link = Director::absoluteURL('Security/lostpassword');
        $str = str_replace($searchString, $link, $str);
        return $str;
    }

    protected function LoginLink($searchString, $str)
    {
        $link = Director::absoluteURL('Security/login');
        $str = str_replace($searchString, $link, $str);
        return $str;
    }

}
