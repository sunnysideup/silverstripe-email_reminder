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
        'Result' => 'Boolean',
        'IsTestOnly' => 'Boolean'
    );

    private static $indexes = array(
        'EmailTo' => true,
        'Result' => true
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

    public static $default_sort = array(
        'Created' => 'DESC'
    );


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
