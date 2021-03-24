<?php

namespace SunnySideUp\EmailReminder\Model;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataObject;

class EmailReminderEmailRecord extends DataObject
{
    private static $singular_name = 'Email Reminder Record';

    private static $plural_name = 'Email Reminder Records';

    private static $table_name = 'EmailReminderEmailRecord';

    private static $db = [
        'EmailTo' => 'Varchar(100)',
        'ExternalRecordClassName' => 'Varchar(100)',
        'ExternalRecordID' => 'Int',
        'Result' => 'Boolean',
        'IsTestOnly' => 'Boolean',
        'EmailContent' => 'HTMLText',
    ];

    private static $indexes = [
        'EmailTo' => true,
        'ExternalRecordClassName' => true,
        'ExternalRecordID' => true,
        'Result' => true,
        'Created' => true,
    ];

    private static $has_one = [
        'EmailReminderNotificationSchedule' => EmailReminderNotificationSchedule::class,
    ];

    private static $summary_fields = [
        'Created.Nice' => 'When',
        'EmailTo' => 'Sent to',
        'Result.NiceAndColourfull' => 'Sent Succesfully',
        'IsTestOnly.NiceAndColourfullInvertedColours' => 'Test Only',
    ];

    private static $default_sort = ['Created' => 'DESC', 'ID' => 'DESC'];

    /**
     * PartialMatchFilter
     */
    private static $searchable_fields = [
        'EmailTo' => 'PartialMatchFilter',
        'Result' => 'ExactMatchFilter',
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

    public function canCreate($member = null, $context = [])
    {
        return false;
    }

    public function canEdit($member = null, $context = [])
    {
        return false;
    }

    public function canDelete($member = null, $context = [])
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
        $fields->addFieldsToTab(
            'Root.Details',
            [
                $fields->dataFieldByName('EmailTo'),
                $fields->dataFieldByName('ExternalRecordClassName'),
                $fields->dataFieldByName('ExternalRecordID'),
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
     * the emails can only be sent once unless previous attempts have failed
     */
    public function canSendAgain()
    {
        $send = true;
        if ($this->Result) {
            if ($this->IsTestOnly) {
                return true;
            }
            $send = false;
            $numberOfSecondsBeforeYouCanSendAgain = $this->EmailReminderNotificationSchedule()->RepeatDays * 86400;
            $todaysTS = strtotime('NOW');

            $creationTS = strtotime($this->Created);
            $difference = $todaysTS - $creationTS;
            if ($difference > $numberOfSecondsBeforeYouCanSendAgain) {
                $send = true;
            }
        }
        return $send;
    }
}
