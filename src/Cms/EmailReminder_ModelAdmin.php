<?php

namespace SunnySideUp\EmailReminder\Cms;

use SilverStripe\Admin\ModelAdmin;
use SunnySideUp\EmailReminder\Model\EmailReminder_NotificationSchedule;

class EmailReminder_ModelAdmin extends ModelAdmin
{
    public static $managed_models = [EmailReminder_NotificationSchedule::class];

    public static $url_segment = 'emailreminders';

    public static $menu_title = 'Email Reminders';

    // public static $menu_priority = 2;

    /* Prevent importing of CSV */
    public $showImportForm = false;
}
