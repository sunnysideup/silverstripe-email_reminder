<?php

class EmailReminder_EmailRecord extends DataObject
{

    private static $singular_name = "Email Reminder Record";
        function i18n_singular_name() { return self::$singular_name;}

    private static $plural_name = "Email Reminder Records";
        function i18n_plural_name() { return self::$plural_name;}

    private static $db = array(
        'EmailTo' => 'Varchar(100)',
        'Result' => 'Boolean',
        'IsTestOnly' => 'Boolean'
    );

    private static $indexes = array(
        'To' => true,
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
     *
     * PartialMatchFilter
     */
    private static $searchable_fields;

    /**
     * e.g.
     *    $controller = singleton("MyModelAdmin");
     *    return $controller->Link().$this->ClassName."/EditForm/field/".$this->ClassName."/item/".$this->ID."/edit";
      */
    public function CMSEditLink()
    {

    }

    public function getCMSFields()
    {

    }

    /**
     * returns list of fields as they are exported
     * @return array
     * Field => Label
     */
    public function getExportFields(){

    }

}
