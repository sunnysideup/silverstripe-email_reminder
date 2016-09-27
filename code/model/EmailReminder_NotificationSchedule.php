<?php

class EmailReminder_NotificationSchedule extends DataObject
{

    /**
     * @var int
     */
    private static $grace_days = 3;

    /**
     * @var string
     */
    private static $default_data_object = 'Member';

    /**
     * @var string
     */
    private static $default_date_field = '';

    /**
     * @var string
     */
    private static $default_email_field = '';
    /**
     * @var string
     */
    private static $replaceable_record_fields = array('FirstName', 'Surname', 'Email');

    /**
     * @var string
     */
    private static $mail_out_class = 'EmailReminder_DailyMailOut';

    private static $singular_name = 'Email Reminder Schedule';
    public function i18n_singular_name()
    {
        return self::$singular_name;
    }

    private static $plural_name = 'Email Reminder Schedules';
    public function i18n_plural_name()
    {
        return self::$plural_name;
    }

    private static $db = array(
        'DataObject' => 'Varchar(100)',
        'EmailField' => 'Varchar(100)',
        'DateField' => 'Varchar(100)',
        'Days' => 'Int',
        'RepeatDays' => 'Int',
        'BeforeAfter' => "Enum('before,after','before')",
        'EmailFrom' => 'Varchar(100)',
        'EmailSubject' => 'Varchar(100)',
        'Content' => 'HTMLText',
        'Disable' => 'Boolean',
        'SendTestTo' => 'Text'
    );


    private static $has_many = array(
        'EmailsSent' => 'EmailReminder_EmailRecord'
    );

    private static $summary_fields = array(
        'EmailSubject',
        'Days'
    );

    private static $field_labels = array(
        'DataObject' => 'Class/Table',
        'Days' => 'Days from Expiry',
        'RepeatDays' => 'Repeat cycle days'
    );

    function populateDefaults(){
        parent::populateDefaults();
        $this->DataObject = $this->Config()->get('default_data_object');
        $this->EmailField = $this->Config()->get('default_email_field');
        $this->DateField = $this->Config()->get('default_date_field');
        $this->Days = 7;
        $this->RepeatDays = 300;
        $this->BeforeAfter = 'before';
        $this->EmailFrom = Config::inst()->get('Email', 'admin_email');
        $this->EmailSubject = 'Your memberships expires in [days] days';
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $emailsSentField = $fields->dataFieldByName('EmailsSent');
        $fields->removeFieldFromTab('Root', 'EmailsSent');

        $fields->addFieldToTab(
            'Root.Main',
            CheckboxField::create('Disable')
        );
        $fields->addFieldToTab(
            'Root.Main',
            $dataObjecField = DropdownField::create(
                'DataObject',
                'Table/Class Name',
                $this->dataObjectOptions()
            )
            ->setRightTitle('Type a valid table/class name')
        );
        if($this->Config()->get('default_data_object')) {
            $fields->replaceField('DataObject', $dataObjecField->performReadonlyTransformation());
        }



        $fields->addFieldToTab(
            'Root.Main',
            $emailFieldField = DropdownField::create(
                'EmailField',
                'Email Field',
                $this->emailFieldOptions()
            )
            ->setRightTitle('Select the field that will contain a valid email address')
            ->setEmptyString('[ Please select ]')
        );
        if($this->Config()->get('default_email_field')) {
            $fields->replaceField('EmailField', $emailFieldField->performReadonlyTransformation());
        }

        $fields->addFieldToTab(
            'Root.Main',
            $dateFieldField = DropdownField::create(
                'DateField',
                'Date Field',
                $this->dateFieldOptions()
            )
            ->setRightTitle('Select a valid Date field to calculate when reminders should be sent')
            ->setEmptyString('[ Please select ]')
        );
        if($this->Config()->get('default_date_field')) {
            $fields->replaceField('DateField', $dateFieldField->performReadonlyTransformation());
        }

        $fields->removeFieldsFromTab(
            'Root.Main',
            array('Days','BeforeAfter', 'RepeatDays')
        );
        $fields->addFieldsToTab(
            'Root.Main',
            array(
                NumericField::create('Days', 'Days')
                    ->setRightTitle('How many days in advance (before) or in arrears (after) of the expiration date should this email be sent?'),
                DropdownField::create('BeforeAfter', 'Before / After Expiration', array('before' => 'before', 'after' => 'after'))
                    ->setRightTitle('Are the days listed above before or after the actual expiration date.'),
                NumericField::create('RepeatDays', 'Repeat Cycle Days')
                    ->setRightTitle('
                        Number of days after which the same reminder can be sent to the same email address.
                        <br />We allow an e-mail to be sent to one specific email address for one specific reminder only once.
                        <br />In this field you can indicate for how long we will apply this rule.'
                )
            )
        );
        $fields->addFieldsToTab(
            'Root.EmailContent',
            array(
                TextField::create('EmailFrom', 'Email From Address')
                    ->setRightTitle('The email from address, eg: "My Company &lt;info@example.com&gt;"'),
                $subjectField = TextField::create('EmailSubject', 'Email Subject Line')
                    ->setRightTitle('The subject of the email'),
                $contentField = HTMLEditorField::create('Content', 'Email Content')
                    ->SetRows(20)
            )
        );
        if($obj = $this->getReplacerObject()) {
            $html = $obj->replaceHelpList($asHTML = true);
            $otherFieldsThatCanBeUsed = $this->getFieldsFromDataObject(array('*'));
            $replaceableFields = $this->Config()->get('replaceable_record_fields');
            if(count($otherFieldsThatCanBeUsed)) {
                $html .= '<h3>You can also use the record fields (not replaced in tests):</h3><ul>';
                foreach($otherFieldsThatCanBeUsed as $key => $value) {
                    if(in_array($key, $replaceableFields)) {
                        $html .= '<li><strong>$'.$key.'</strong> <span>'.$value.'</span></li>';
                    }
                }
            }
            $html .= '</ul>';
            $subjectField->setRightTitle('for replacement options, please see below ...');
            $contentField->setRightTitle($html);
        }
        $fields->addFieldsToTab(
            'Root.Sent',
            array(
                TextareaField::create('SendTestTo', 'Send test email to ...')
                    ->setRightTitle('
                        Separate emails by commas, a test email will be sent every time you save this Email Reminder, if you do not want test emails to be sent make sure this field is empty
                        '
                    )
                    ->SetRows(3)
            )
        );
        if($emailsSentField) {
            $config = $emailsSentField->getConfig();
            $config->removeComponentsByType('GridFieldAddExistingAutocompleter');
            $fields->addFieldToTab(
                'Root.Sent',
                $emailsSentField
            );
        }
        $records = $this->CurrentRecords();
        if($records) {
            $fields->addFieldsToTab(
                'Root.Review',
                array(
                    GridField::create(
                        'CurrentRecords',
                        'Today we are sending to ...',
                        $records
                    ),
                    LiteralField::create(
                        'SampleFieldDataForRecords',
                        '<h3>sample of date field values:</h3>
                        <li>'.implode('</li><li>', $this->SampleFieldDataForRecords()).'</li>'
                    )
                )
            );
        }
        return $fields;
    }

    /**
     * @return array
     */
    protected function dataObjectOptions(){
        return ClassInfo::subclassesFor("DataObject");
    }

    /**
     * @return array
     */
    protected function emailFieldOptions(){
        return $this->getFieldsFromDataObject(array('Varchar', 'Email'));
    }

    /**
     * @return array
     */
    protected function dateFieldOptions(){
        return $this->getFieldsFromDataObject(array('Date'));
    }


    /**
     * list of database fields available
     * @param  array $fieldTypeMatchArray - strpos filter
     * @return array
     */
    protected function getFieldsFromDataObject($fieldTypeMatchArray = array()){
        $array = array();
        if($this->hasValidDataObject()) {
            $object = Injector::inst()->get($this->DataObject);
            if($object) {
                $allOptions = $object->stat('db');
                $fieldLabels = $object->fieldLabels();
                foreach($allOptions as $fieldName => $fieldType) {
                    foreach($fieldTypeMatchArray as $matchString) {
                        if((strpos($fieldType, $matchString) !== false) || $matchString == '*' ) {
                            if(isset($fieldLabels[$fieldName])) {
                                $label = $fieldLabels[$fieldName];
                            } else {
                                $label = $fieldName;
                            }
                            $array[$fieldName] = $label;
                        }
                    }
                }
            }
        }
        return $array;
    }


    /**
     * Test if valid classname has been set
     * @param null
     * @return Boolean
     */
    public function hasValidDataObject()
    {
        return ( ! $this->DataObject) || ClassInfo::exists($this->DataObject) ? true : false;
    }

    /**
     * Test if valid fields have been set
     * @param null
     * @return Boolean
     */
    public function hasValidDataObjectFields()
    {
        if (!$this->hasValidDataObject()) {
            return false;
        }
        $emailFieldOptions = $this->emailFieldOptions();
        if(!isset($emailFieldOptions[$this->EmailField])) {
            return false;
        }
        $dateFieldOptions = $this->dateFieldOptions();
        if(!isset($dateFieldOptions[$this->DateField])) {
            return false;
        }
        return true;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return (
            $this->hasValidDataObjectFields()) ?
            '[' . $this->EmailSubject . '] // send ' . $this->Days . ' days '.$this->BeforeAfter.' Expiration Date'
            :
            'uncompleted';
    }

    /**
     * @return boolean
     */
    function hasValidFields()
    {
        if (!$this->hasValidDataObject()) {
            return false;
        }
        if (!$this->hasValidDataObjectFields()) {
            return false;
        }
        if($this->EmailFrom && $this->EmailSubject && $this->Content) {
            return true;
        }
        return false;
    }

    /**
     * @return boolean
     */
    public function validate()
    {
        $valid = parent::validate();
        if( $this->exists()) {
            if ( ! $this->hasValidDataObject()) {
                $valid->error('Please enter valid Tabe/Class name ("' . htmlspecialchars($this->DataObject) .'" does not exist)');
            } elseif (! $this->hasValidDataObjectFields()) {
                $valid->error('Please select valid fields for both Email & Date');
            } elseif ( ! $this->hasValidFields()) {
                $valid->error('Please fill in all fields.  Make sure not to forget the email details (from who, subject, content)');
            }
        }
        return $valid;
    }

    function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if($this->RepeatDays < ($this->Days * 3)) {
            $this->RepeatDays = ($this->Days * 3);
        }
    }

    function onAfterWrite()
    {
        parent::onAfterWrite();
        if($this->SendTestTo) {
            if($mailOutObject = $this->getMailOutObject()) {
                $mailOutObject->setTestOnly(true);
                $mailOutObject->setVerbose(true);
                $mailOutObject->run(null);
            }
        }
    }

    /**
     *
     * @return null | EmailReminder_ReplacerClassInterface
     */
    function getReplacerObject()
    {
        if($mailOutObject = $this->getMailOutObject()) {
            return $mailOutObject->getReplacerObject();
        }
    }

    /**
     *
     * @return null | ScheduledTask
     */
    function getMailOutObject()
    {
        $mailOutClass = $this->Config()->get('mail_out_class');
        if(class_exists($mailOutClass)) {
            $obj = Injector::inst()->get($mailOutClass);
            if($obj instanceof BuildTask){
                return $obj;
            } else {
                user_error($mailOutClass.' needs to be an instance of a Scheduled Task');
            }
        }
    }

    /**
     * @param int $limit
     * @return array
     */
    function SampleFieldDataForRecords($limit = 100){
        if($this->hasValidFields()) {
            $do = $this->DataObject;
            $do = $do::get();
            if($do->count()) {
                return array_unique($do->sort($this->DateField, 'DESC')->limit($limit)->column($this->DateField));
            }
            else {
                return array();
            }

        }
    }


    /**
     *
     * @return DataList
     */
    function CurrentRecords(){
        if($this->hasValidFields()) {
            $do = $this->DataObject;
            $sign = $this->BeforeAfter == 'before' ? '+' : '-';
            $graceDays = Config::inst()->get('EmailReminder_NotificationSchedule', 'grace_days');

            if($sign == '+') {
                $minDays = $sign . ($this->Days - $graceDays) . ' days';
                $maxDays = $sign . $this->Days . ' days';
            } else {
                $minDays = $sign . ($this->Days - $graceDays) . ' days';
                $maxDays = $sign . $this->Days . ' days';
            }

            $minDate = date('Y-m-d', strtotime($minDays)).' 00:00:00';
            $maxDate = date('Y-m-d', strtotime($maxDays)).' 23:59:59';

            // Use StartsWith to match Date and DateTime fields
            $records = $do::get()
                ->where('("'. $this->DateField.'" BETWEEN \''.$minDate.'\' AND \''.$maxDate.'\')');
           return $records;
        }
    }


}
