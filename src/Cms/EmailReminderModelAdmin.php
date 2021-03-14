<?php

namespace SunnySideUp\EmailReminder\Cms;

use SilverStripe\Admin\ModelAdmin;
use SunnySideUp\EmailReminder\Model\EmailReminderNotificationSchedule;

class EmailReminderModelAdmin extends ModelAdmin
{

    /* Prevent importing of CSV */
    public $showImportForm = false;

    private static $managed_models = [
        EmailReminderNotificationSchedule::class,
    ];

    private static $url_segment = 'emailreminders';

    private static $menu_title = 'Email Reminders';
}
