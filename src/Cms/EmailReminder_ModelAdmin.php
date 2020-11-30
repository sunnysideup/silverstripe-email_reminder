<?php

namespace SunnySideUp\EmailReminder\Cms;


use SunnySideUp\EmailReminder\Model\EmailReminder_NotificationSchedule;
use SilverStripe\Admin\ModelAdmin;



class EmailReminder_ModelAdmin extends ModelAdmin
{
    public static $managed_models = array(EmailReminder_NotificationSchedule::class);

    public static $url_segment = 'emailreminders';

    public static $menu_title = 'Email Reminders';

    // public static $menu_priority = 2;

    /* Prevent importing of CSV */
    public $showImportForm = false;
}

