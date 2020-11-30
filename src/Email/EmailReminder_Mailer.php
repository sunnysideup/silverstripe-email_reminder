<?php

namespace SunnySideUp\EmailReminder\Email;




use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SunnySideUp\EmailReminder\Email\EmailReminder_Mailer;
use SilverStripe\Control\Email\Mailer;




class EmailReminder_Mailer extends Mailer
{
    private static $css_file = 'email_reminder/css/example.css';

    public function sendHTML(
        $to,
        $from,
        $subject,
        $htmlContent,
        $attachedFiles = false,
        $customheaders = false,
        $plainContent = false
    ) {
        $cssFileLocation = Director::baseFolder() .'/'. Config::inst()->get(EmailReminder_Mailer::class, "css_file");
        if ($cssFileLocation) {
            if (file_exists($cssFileLocation)) {
                $cssFileHandler = fopen($cssFileLocation, 'r');
                $css = fread($cssFileHandler, filesize($cssFileLocation));
                fclose($cssFileHandler);
                $emog = new \Pelago\Emogrifier($htmlContent, $css);
                $htmlContent = $emog->emogrify();
            }
        }
        return parent::sendHTML(
            $to,
            $from,
            $subject,
            $htmlContent,
            $attachedFiles,
            $customheaders,
            $plainContent
        );
    }
}

