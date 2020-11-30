<?php

namespace SunnySideUp\EmailReminder\Cms;

use ModelAdmin;


class EmailReminder_ModelAdmin extends ModelAdmin
{
    public static $managed_models = array('EmailReminder_NotificationSchedule');

    public static $url_segment = 'emailreminders';

    public static $menu_title = 'Email Reminders';

    // public static $menu_priority = 2;

    /* Prevent importing of CSV */
    public $showImportForm = false;
}

