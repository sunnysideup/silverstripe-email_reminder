<?php

namespace SunnySideUp\EmailReminder\Cms;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Security\PermissionProvider;
use SunnySideUp\EmailReminder\Model\EmailReminderEmailRecord;
use SunnySideUp\EmailReminder\Model\EmailReminderNotificationSchedule;

/**
 * Class \SunnySideUp\EmailReminder\Cms\EmailReminderModelAdmin
 *
 */
class EmailReminderModelAdmin extends ModelAdmin implements PermissionProvider
{
    // Prevent importing of CSV
    public $showImportForm = false;

    private static $managed_models = [
        EmailReminderNotificationSchedule::class,
        EmailReminderEmailRecord::class,
    ];

    public const PERMISSION_PROVIDER_CODE = 'Email Reminders';

    private static $url_segment = 'emailreminders';

    private static $menu_title = 'Email Reminders';

    private static $icon = 'font-icon-mail';

    public function providePermissions()
    {
        return [
            self::PERMISSION_PROVIDER_CODE => [
                'name' => 'Access to Email Reminders',
                'category' => 'Email Reminders',
                'sort' => 100,
                'help' => 'Allow user to manage email reminders',
            ]
        ];
    }
}
