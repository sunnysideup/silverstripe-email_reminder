<?php

class EmailReminder_NotificationSchedule extends DataObject
{

    private static $days_before_same_notification_can_be_sent_to_same_user = 100;

    private static $singular_name = 'Email Reminder Notification Schedule';
    public function i18n_singular_name()
    {
        return self::$singular_name;
    }

    private static $plural_name = 'Email Reminder Notification Schedules';
    public function i18n_plural_name()
    {
        return self::$plural_name;
    }

    private static $db = array(
        'DataObject' => 'Varchar(100)',     // ClassName
        'EmailField' => 'Varchar(100)',     // Field that contains an email
        'DateField' => 'Varchar(100)',      // Field that contains a date
        'Days' => 'Int',                    // Days in advance
        'EmailFrom' => 'Varchar(100)',      // From Address
        'EmailSubject' => 'Varchar(100)',   // Email content
        'Content' => 'HTMLText',            // Email content
    );

    private static $has_one;

    /**
     * A meta-relationship that allows you to define the reverse side of a {@link DataObject::$has_one}.
     *
     * This does not actually create any data structures, but allows you to query the other object in a one-to-one
     * relationship from the child object. If you have multiple belongs_to links to another object you can use the
     * syntax "ClassName.HasOneName" to specify which foreign has_one key on the other object to use.
     *
     * Note that you cannot have a has_one and belongs_to relationship with the same name.
     *
     * @var array
     * @config
     */
    private static $belongs_to;

    private static $has_many;

    private static $many_many;

    private static $belongs_many_many;

    private static $casting;

    private static $indexes;

    private static $default_sort;

    private static $required_fields;

    private static $summary_fields = array(
        'DataObject',
        'EmailSubject',
        'Days'
    );

    private static $field_labels = array(
        'DataObject' => 'Class/Table',
        'Days' => 'Send x Days Before'
    );

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->removeByName('EmailField');
        $fields->removeByName('DateField');
        $fields->removeByName('Days');
        $fields->removeByName('EmailFrom');
        $fields->removeByName('EmailSubject');
        $fields->removeByName('Content');

        if ($this->exists() && $this->hasValidDataObject()) {
            $raw_structure = DataObject::database_fields($this->DataObject);
            $structure = [];
            foreach ($raw_structure as $key => $type) {
                if ($key != 'ClassName') {
                    $structure[$key] = $key;
                }
            }

            $fields->addFieldsToTab('Root.Main', array(
                TextField::create('DataObject', 'Table/Class Name')
                    ->setRightTitle('Type a valid table/class name'),
                DropdownField::create('EmailField', 'Email Field', $structure)
                    ->setRightTitle('Select the field that will contain a valid email address')
                    ->setEmptyString('[ Please select ]'),
                DropdownField::create('DateField', 'Date Field', $structure)
                    ->setRightTitle('Select a valid Date field to calculate when reminders should be sent')
                    ->setEmptyString('[ Please select ]')
            ));

            if ($this->hasValidFields()) {
                $fields->addFieldsToTab('Root.Main', array(
                    NumericField::create('Days', 'Days Before Date To Email')
                        ->setRightTitle('How many days in advance should this email be sent'),
                    TextField::create('EmailFrom', 'Email From Address')
                        ->setRightTitle('The email from address, eg: "My Company &lt;info@example.com&gt;"'),
                    TextField::create('EmailSubject', 'Email Subject Line')
                        ->setRightTitle('The subject of the email'),
                    HTMLEditorField::create('Content', 'Email Content')
                        ->setRightTitle('The content of the email. You can use any fields in your data such as $Name,' .
                            ' $Email etc including methods.')
                        ->SetRows(20)
                ));
            }
        } else {
            $fields->addFieldToTab('Root.Main',
                TextField::create('DataObject', 'Table/Class Name')
                    ->setRightTitle('Type a valid table/class name')
            );
        }

        return $fields;
    }

    /**
     * Test if valid classname has been set
     * @param null
     * @return Boolean
     */
    public function hasValidDataObject()
    {
        return ClassInfo::exists($this->DataObject) ? true : false;
    }

    /**
     * Test if valid fields have been set
     * @param null
     * @return Boolean
     */
    public function hasValidFields()
    {
        if (!$this->hasValidDataObject()) {
            return false;
        }
        $raw_structure = DataObject::database_fields($this->DataObject);
        // We can't test for valid email data
        if (!isset($raw_structure[$this->EmailField])) {
            return false;
        }
        // Test of field type matches '/date/i'
        if (!isset($raw_structure[$this->DateField]) || !preg_match('/date/i', $raw_structure[$this->DateField])) {
            return false;
        }
        return true;
    }

    public function getTitle()
    {
        return ($this->hasValidFields()) ? $this->DataObject . ' [' . $this->EmailSubject . '] - ' . $this->Days . ' days' : false;
    }


    public function validate()
    {
        $valid = parent::validate();
        if (!$this->DataObject || !$this->hasValidDataObject()) {
            $valid->error('Please enter valid Tabe/Class name ("' . htmlspecialchars($this->DataObject) .'" does not exist)');
        } elseif ($this->exists() && !$this->hasValidFields()) {
            $valid->error('Please select valid fields for both Email & Date');
        } elseif ($this->exists() && !$this->EmailFrom || !$this->EmailSubject || !$this->Content) {
            $valid->error('Please fill in all fields');
        }

        return $valid;
    }

    /* Set default of 7 days */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if (!$this->exists()) {
            $this->Days = 7;
        }
    }
}
