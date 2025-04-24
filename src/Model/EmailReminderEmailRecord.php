<?php

namespace SunnySideUp\EmailReminder\Model;

use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use Sunnysideup\CmsEditLinkField\Forms\Fields\CMSEditLinkField;
use SunnySideUp\EmailReminder\Api\EmailReminderMailOut;
use SunnySideUp\EmailReminder\Cms\EmailReminderModelAdmin;

/**
 * Class \SunnySideUp\EmailReminder\Model\EmailReminderEmailRecord
 *
 * @property string $EmailTo
 * @property string $EmailCc
 * @property string $EmailBcc
 * @property string $ExternalRecordClassName
 * @property int $ExternalRecordID
 * @property bool $HasTried
 * @property bool $Result
 * @property bool $IsTestOnly
 * @property string $Subject
 * @property string $EmailContent
 * @property int $EmailReminderNotificationScheduleID
 * @method EmailReminderNotificationSchedule EmailReminderNotificationSchedule()
 */
class EmailReminderEmailRecord extends DataObject
{
    private static $singular_name = 'Email Reminder Record';

    private static $plural_name = 'Email Reminder Records';

    private static $table_name = 'EmailReminderEmailRecord';

    private static $db = [
        'EmailTo' => 'Varchar(100)',
        'EmailCc' => 'Text',
        'EmailBcc' => 'Text',
        'ExternalRecordClassName' => 'Varchar(255)',
        'ExternalRecordID' => 'Int',
        'HasTried' => 'Boolean',
        'Result' => 'Boolean',
        'IsTestOnly' => 'Boolean',
        'Subject' => 'Varchar',
        'EmailContent' => 'HTMLText',
    ];

    private static $indexes = [
        'EmailTo' => true,
        'ExternalRecordClassName' => true,
        'ExternalRecordID' => true,
        'Result' => true,
        'HasTried' => true,
        'Created' => true,
    ];

    private static $field_labels = [
        'EmailTo' => 'To',
        'ExternalRecordClassName' => true,
        'ExternalRecordID' => true,
        'HasTried' => 'Tried to sent',
        'Result' => 'Has been sent',
    ];

    private static $has_one = [
        'EmailReminderNotificationSchedule' => EmailReminderNotificationSchedule::class,
    ];

    private static $summary_fields = [
        'Created.Nice' => 'When',
        'EmailTo' => 'Sent to',
        'Subject' => 'Subject',
        'HasTried.NiceAndColourfull' => 'Has Sent',
        'Result.NiceAndColourfull' => 'Sent Succesfully',
        'IsTestOnly.NiceAndColourfullInvertedColours' => 'Test Only',
    ];

    private static $default_sort = ['Created' => 'DESC', 'ID' => 'DESC'];

    /**
     * PartialMatchFilter.
     */
    private static $searchable_fields = [
        'EmailTo' => 'PartialMatchFilter',
        'Result' => 'ExactMatchFilter',
        'Subject' => 'ExactMatchFilter',
        'HasTried' => 'ExactMatchFilter',
        'IsTestOnly' => 'ExactMatchFilter',
    ];

    public function i18n_singular_name()
    {
        return self::$singular_name;
    }

    public function i18n_plural_name()
    {
        return self::$plural_name;
    }

    public function canView($member = null)
    {
        return Permission::check(EmailReminderModelAdmin::PERMISSION_PROVIDER_CODE, 'any', $member);
    }

    public function canCreate($member = null, $context = [])
    {
        return false;
    }

    public function canEdit($member = null)
    {
        return false;
    }

    public function canDelete($member = null)
    {
        return false;
    }

    /**
     * standard SS method.
     *
     * @return FieldList
     */
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $linkedObject = $this->FindLinkedObject();
        $fields->removeByName('ExternalRecordClassName');
        $fields->removeByName('ExternalRecordID');
        $fields->addFieldsToTab(
            'Root.Details',
            [
                CMSEditLinkField::create('LinksTo', 'Linked To', $linkedObject),
                $fields->dataFieldByName('HasTried'),
                $fields->dataFieldByName('Result'),
                $fields->dataFieldByName('IsTestOnly'),
                $fields->dataFieldByName('EmailReminderNotificationScheduleID'),
            ]
        );
        $fields->replaceField(
            'EmailContent',
            LiteralField::create(
                'EmailContent',
                $this->EmailContent
            )
        );

        return $fields;
    }

    /**
     * tests to see if an email can be sent
     * the emails can only be sent once unless previous attempts have failed.
     */
    public function canSendAgain(): bool
    {
        $schedule = $this->EmailReminderNotificationSchedule();
        $canSendAgain = true;
        if ($this->Result) {
            if ($this->IsTestOnly) {
                return true;
            }

            $canSendAgain = false;
            if ($schedule->IsImmediate()) {
                $repeatValue = Config::inst()->get(EmailReminderMailOut::class, 'grace_days_for_immediate_emails'); // 2 minute grace period;
            } else {
                $repeatValue = $schedule->RepeatDays;
            }
            if ($repeatValue) {
                $numberOfSecondsBeforeYouCanSendAgain = $repeatValue * 86400;
                $nowTs = strtotime('NOW');
                $creationTS = strtotime($this->Created);
                $difference = $nowTs - $creationTS;
                if ($difference > $numberOfSecondsBeforeYouCanSendAgain) {
                    $canSendAgain = true;
                }
            }
        }

        return $canSendAgain;
    }

    /**
     * @return mixed (DataObject)
     */
    public function FindLinkedObject()
    {
        $linkedObject = null;
        if (class_exists($this->ExternalRecordClassName)) {
            $className = $this->ExternalRecordClassName;
            $linkedObject = $className::get()->byID($this->ExternalRecordID);
        }

        return $linkedObject ?: $this;
    }
}
