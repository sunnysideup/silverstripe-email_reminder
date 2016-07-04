<?php


class EmailReminder_Mailer extends Mailer
{

    private static $css_file = '';

    private static $replacer_class = '';

    function sendHTML(
        $to,
        $from,
        $subject,
        $htmlContent,
        $attachedFiles = false,
        $customheaders = false,
        $plainContent = false
    ) {
        $replacerClass = Director::baseFolder() . Config::inst()->get("EmailReminder_Mailer", "replacer_class");
        if($replacerClass && class_exists($replacerClass)) {
            $interfaces = class_implements($replacerClass);
            if($interfaces && in_array('EmailReminder_ReplacerClassInterface', $interfaces)) {
                $replacerObject = Injector::inst()->get($replacerClass);
                $htmlContent = $replacerObject->replace($htmlContent);
                $subject = $replacerObject->replace($subject);
            }
        }
        $cssFileLocation = Director::baseFolder() . Config::inst()->get("EmailReminder_Mailer", "css_file");
        if($cssFileLocation) {
            $cssFileHandler = fopen($cssFileLocation, 'r');
            $css = fread($cssFileHandler,  filesize($cssFileLocation));
            fclose($cssFileHandler);
            $emog = new \Pelago\Emogrifier($htmlContent, $css);
            $htmlContent = $emog->emogrify();
        }
        return parent::sendHTML($to, $from, $subject, $htmlContent, $attachedFiles, $customheaders, $plainContent);
    }

}
