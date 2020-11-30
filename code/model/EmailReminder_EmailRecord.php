<?php

class EmailReminder_EmailRecord extends DataObject
{
    private static $singular_name = "Email Reminder Record";
    public function i18n_singular_name()
    {
        return self::$singular_name;
    }

    private static $plural_name = "Email Reminder Records";
    public function i18n_plural_name()
    {
        return self::$plural_name;
    }

    private static $db = array(
        'EmailTo' => 'Varchar(100)',
        'ExternalRecordClassName' => 'Varchar(100)',
        'ExternalRecordID' => 'Int',
        'Result' => 'Boolean',
        'IsTestOnly' => 'Boolean',
        'EmailContent' => 'HTMLText'
    );

    private static $indexes = array(
        'EmailTo' => true,
        'ExternalRecordClassName' => true,
        'ExternalRecordID' => true,
        'Result' => true,
        'Created' => true
    );

    private static $has_one = array(
        'EmailReminder_NotificationSchedule' => 'EmailReminder_NotificationSchedule'
    );

    private static $summary_fields = array(
        'Created.Nice' => 'When',
        'EmailTo' => 'Sent to',
        'Result.Nice' => 'Sent Succesfully',
        'IsTestOnly.Nice' => 'Test Only'
    );

    public static $default_sort = [
        'Created' => 'DESC',
        'ID' => 'DESC'
    ];


    public function canCreate($member = null)
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
        $fields->addFieldsToTab(
            'Root.Details',
            array(
                $fields->dataFieldByName("EmailTo"),
                $fields->dataFieldByName("ExternalRecordClassName"),
                $fields->dataFieldByName("ExternalRecordID"),
                $fields->dataFieldByName("Result"),
                $fields->dataFieldByName("IsTestOnly"),
                $fields->dataFieldByName("EmailReminder_NotificationScheduleID"),
            )
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
     *
     * tests to see if an email can be sent
     * the emails can only be sent once unless previous attempts have failed
     */
    public function canSendAgain()
    {
        $send = true;
        if ($this->Result) {
            if ($this->IsTestOnly) {
                return true;
            } else {
                $send = false;
                $numberOfSecondsBeforeYouCanSendAgain = $this->EmailReminder_NotificationSchedule()->RepeatDays * 86400;
                $todaysTS = strtotime('NOW');

                $creationTS = strtotime($this->Created);
                $difference = ($todaysTS - $creationTS);
                if ($difference > $numberOfSecondsBeforeYouCanSendAgain) {
                    $send = true;
                }
            }
        }
        return $send;
    }

    /**
     *
     * PartialMatchFilter
     */
    private static $searchable_fields = array(
        'EmailTo' => 'PartialMatchFilter',
        'Result' => 'ExactMatchFilter',
        'IsTestOnly' => 'ExactMatchFilter',
    );

    /**
     * e.g.
     *    $controller = singleton("MyModelAdmin");
     *    return $controller->Link().$this->ClassName."/EditForm/field/".$this->ClassName."/item/".$this->ID."/edit";
      */
    public function CMSEditLink()
    {
    }


    /**
     * returns list of fields as they are exported
     * @return array
     * Field => Label
     */
    public function getExportFields()
    {
    }
}

