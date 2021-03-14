<?php

namespace SunnySideUp\EmailReminder\Email;

use Pelago\Emogrifier\CssInliner;
use SilverStripe\Control\Director;

use SilverStripe\Control\Email\Email;
use SilverStripe\Core\Config\Config;

class EmailReminderMailer
{
    private static $css_file = 'vendor/sunnysideup/email_reminder/client/css/example.css';

    public function sendHTML(
        $to,
        $from,
        $subject,
        $htmlContent
    ) {
        $cssFileLocation = Director::baseFolder() . '/' . Config::inst()->get(EmailReminderMailer::class, 'css_file');
        if ($cssFileLocation) {
            if (file_exists($cssFileLocation)) {
                $css = file_get_contents($cssFileLocation);
                $htmlContent = CssInliner::fromHtml($htmlContent)->inlineCss($css)->render();
            }
        }
        (new Email())
            ->setTo($to)
            ->setFrom($from)
            ->setSubject($subject)
            ->setBody($htmlContent)
            ->send();
    }
}
