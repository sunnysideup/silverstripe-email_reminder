<?php

namespace SunnySideUp\EmailReminder\Cms;

use SilverStripe\Admin\ModelAdmin;
use SunnySideUp\EmailReminder\Model\EmailReminderEmailRecord;
use SunnySideUp\EmailReminder\Model\EmailReminderNotificationSchedule;

/**
 * Class \SunnySideUp\EmailReminder\Cms\EmailReminderModelAdmin
 *
 */
class EmailReminderModelAdmin extends ModelAdmin
{
    // Prevent importing of CSV
    public $showImportForm = false;

    private static $managed_models = [
        EmailReminderNotificationSchedule::class,
        EmailReminderEmailRecord::class,
    ];

    private static $url_segment = 'emailreminders';

    private static $menu_title = 'Email Reminders';
}
