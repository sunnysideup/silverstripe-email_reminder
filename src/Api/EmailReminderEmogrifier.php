<?php

namespace SunnySideUp\EmailReminder\Api;

use Pelago\Emogrifier\CssInliner;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\View\ViewableData;

class EmailReminderEmogrifier extends ViewableData
{
    private static $css_file = 'vendor/sunnysideup/email_reminder/client/css/example.css';

    public static function emogrify(string $htmlContent): string
    {
        $cssFile = Config::inst()->get(EmailReminderEmogrifier::class, 'css_file');
        if ('' !== $cssFile) {
            $cssFileLocation = Director::baseFolder() . '/' . $cssFile;
            if (file_exists($cssFileLocation)) {
                $css = file_get_contents($cssFileLocation);
                $htmlContent = CssInliner::fromHtml($htmlContent)->inlineCss($css)->render();
            }
        }

        return $htmlContent;
    }
}
