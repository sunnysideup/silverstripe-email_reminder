<?php

namespace SunnySideUp\EmailReminder\Model;

use SilverStripe\Control\Email\Email;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\TextareaField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Security\Member;
use SunnySideUp\EmailReminder\Cms\EmailReminderModelAdmin;
use SunnySideUp\EmailReminder\Interfaces\EmailReminderMailOutInterface;
use SunnySideUp\EmailReminder\Interfaces\EmailReminderReplacerClassInterface;
use SunnySideUp\EmailReminder\Tasks\EmailReminderDailyMailOut;
use Sunnysideup\SanitiseClassName\Sanitiser;
use SunnySideUp\EmailReminder\Api\EmailReminderMailOut;
use SilverStripe\Control\Director;

class EmailReminderNotificationSchedule extends DataObject
{

    protected const BEFORE_NOW_AFTER_ARRAY = [
        'before' => 'before',
        'after' => 'after',
        'immediately' => 'immediately',
    ];

    /**
     * @var int
     */
    private static $grace_days = 10;

    /**
     * @var string
     */
    private static $default_data_object = Member::class;

    /**
     * @var string
     */
    private static $default_date_field = '';

    /**
     * @var string
     */
    private static $default_email_field = '';

    /**
     * @var array[string]
     */
    private static $replaceable_record_fields = [
        'FirstName',
        'Surname',
        'Email'
    ];

    /**
     * @var string
     */
    private static $include_method = 'EmailReminderInclude';

    /**
     * @var string
     */
    private static $exclude_method = 'EmailReminderExclude';

    /**
     * @var string
     */
    private static $mail_out_class = EmailReminderMailOut::class;

    /**
     * @var string
     */
    private static $disabled_checkbox_label = 'Disable';

    /**
     * @var string
     */
    private static $singular_name = 'Email Reminder Schedule';

    /**
     * @var string
     */
    private static $plural_name = 'Email Reminder Schedules';

    /**
     * @var string
     */
    private static $table_name = 'EmailReminderNotificationSchedule';

    private static $db = [
        'Code' => 'Varchar(20)',
        'DataObject' => 'Varchar(255)',
        'EmailField' => 'Varchar(100)',
        'DateField' => 'Varchar(100)',
        'Days' => 'Int',
        'RepeatDays' => 'Int',
        'BeforeAfter' => "Enum('before,after,immediately','before')",
        'EmailFrom' => 'Varchar(100)',
        'EmailSubject' => 'Varchar(100)',
        'Content' => 'HTMLText',
        'Disable' => 'Boolean',
        'SendTestTo' => 'Text',
    ];

    private static $has_many = [
        'EmailsSent' => EmailReminderEmailRecord::class,
    ];

    private static $summary_fields = [
        'EmailSubject',
        'BeforeAfter',
        'Days',
    ];

    private static $field_labels = [
        'DataObject' => 'Class/Table',
        'Days' => 'Days Before/After Date',
        'RepeatDays' => 'Repeat cycle days',
        'BeforeAfter' => 'When to send',
    ];

    private static $indexes = [
        'Code' => true,
    ];

    public function sendOne($recordOrEmail, ?bool $isTestOnly = false, ?bool $force = false) : bool
    {
        $mailerClassName = Config::inst()->get(EmailReminderNotificationSchedule::class, 'mail_out_class');
        $mailer = Injector::inst()->get($mailerClassName);
        return $mailer->send($this, $isTestOnly, $force);
    }

    public function IsImmediate() : bool
    {
        return $this->BeforeAfter === 'immediately';
    }

    public function i18n_singular_name()
    {
        return self::$singular_name;
    }

    public function i18n_plural_name()
    {
        return self::$plural_name;
    }

    public function populateDefaults()
    {
        $return = parent::populateDefaults();
        $this->DataObject = $this->Config()->get('default_data_object');
        $this->EmailField = $this->Config()->get('default_email_field');
        $this->DateField = $this->Config()->get('default_date_field');
        $this->Days = 7;
        $this->RepeatDays = 300;
        $this->BeforeAfter = 'before';
        $this->EmailFrom = Config::inst()->get(Email::class, 'admin_email');
        $this->EmailSubject = 'Your memberships expires in [days] days';

        return $return;
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $emailsSentField = $fields->dataFieldByName('EmailsSent');
        $fields->removeFieldFromTab('Root', 'EmailsSent');

        $disableLabel = $this->Config()->get('disabled_checkbox_label');
        $fields->addFieldToTab(
            'Root.Main',
            CheckboxField::create('Disable', $disableLabel)->setDescription('If checked this email will not be sent during the daily mail out, instead it will be sent after an event like a form submission.')
        );
        $whatIsThis = 'UNKNNOWN';
        $obj = Injector::inst()->get($this->DataObject);
        if ($obj) {
            $whatIsThis = $obj->i18n_singular_name();
        }
        $fields->addFieldToTab(
            'Root.Main',
            $dataObjectField = DropdownField::create(
                'DataObject',
                'Works on ... ',
                $this->dataObjectOptions()
            )
                ->setDescription('This is a : ' . $whatIsThis)
        );
        if ($this->Config()->get('default_data_object')) {
            $fields->replaceField('DataObject', ReadonlyField ::create('DataObjectNice', 'Works on ...', $whatIsThis));
        }

        $fields->addFieldToTab(
            'Root.Main',
            $emailFieldField = DropdownField::create(
                'EmailField',
                'Email Field',
                $this->emailFieldOptions()
            )
                ->setDescription('Select the field that will contain a valid email address')
                ->setEmptyString('[ Please select ]')
        );
        if ($this->Config()->get('default_email_field')) {
            $fields->replaceField('EmailField', $emailFieldField->performReadonlyTransformation());
        }

        $fields->addFieldToTab(
            'Root.Main',
            $dateFieldField = DropdownField::create(
                'DateField',
                'Date Field',
                $this->dateFieldOptions()
            )
                ->setDescription('Select a valid Date field to calculate when reminders should be sent')
                ->setEmptyString('[ Please select ]')
        );

        if ($this->Config()->get('default_date_field')) {
            $fields->replaceField('DateField', $dateFieldField->performReadonlyTransformation());
        }

        $fields->removeFieldsFromTab(
            'Root.Main',
            ['Days', 'BeforeAfter', 'RepeatDays']
        );
        $fields->addFieldsToTab(
            'Root.Main',
            [
                DropdownField::create('BeforeAfter', 'Before / After Expiration', self::BEFORE_NOW_AFTER_ARRAY)
                    ->setDescription('Are the days listed above before or after the actual expiration date.'),

                NumericField::create('Days', 'Days')
                    ->setDescription(
                        DBField::create_field(
                            'HTMLText',
                            'How many days in advance (before) or in arrears (after) of the expiration date should this email be sent? </br>This field is ignored if set to send immediately.'
                        )
                    )->setScale(0),

                NumericField::create('RepeatDays', 'Repeat Cycle Days')
                    ->setDescription(
                        DBField::create_field(
                            'HTMLText',
                            '
                            Number of days after which the same reminder can be sent to the same email address.
                            <br />We allow an e-mail to be sent to one specific email address for one specific reminder only once.
                            <br />In this field you can indicate for how long we will apply this rule.'
                        )
                    )->setScale(0),
            ]
        );

        if ($this->IsImmediate()) {
            $fields->removeFieldsFromTab(
                'Root.Main',
                [
                    'Days',
                    'RepeatDays'
                ]
            );
        }

        $fields->addFieldsToTab(
            'Root.EmailContent',
            [
                TextField::create('EmailFrom', 'Email From Address')
                    ->setDescription('The email from address, eg: "My Company info@example.com"'),
                $subjectField = TextField::create('EmailSubject', 'Email Subject Line')
                    ->setDescription('The subject of the email'),
                $contentField = HTMLEditorField::create('Content', 'Email Content')
                    ->SetRows(20),
            ]
        );
        $obj = $this->getReplacerObject();
        if (null !== $obj) {
            $html = $obj->replaceHelpList($asHTML = true);
            $otherFieldsThatCanBeUsed = $this->getFieldsFromDataObject(['*']);
            $replaceableFields = $this->Config()->get('replaceable_record_fields');
            foreach ($otherFieldsThatCanBeUsed as $key => $value) {
                if (in_array($key, $replaceableFields, true)) {
                    $html .= '<li><strong>$' . $key . '</strong> <span>' . $value . '</span></li>';
                }
            }
            $html .= '</ul><hr /><hr /><hr />';
            $subjectField->setDescription('for replacement options, please see below ...');
            $contentField->setDescription(
                DBField::create_field(
                    'HTMLText',
                    $html
                )
            );
        }
        $fields->addFieldsToTab(
            'Root.Sent',
            [
                TextareaField::create('SendTestTo', 'Send test email to ...')
                    ->setDescription(
                        '
                        Separate emails by commas, a test email will be sent every time you save this Email Reminder, if you do not want test emails to be sent make sure this field is empty
                        '
                    )
                    ->SetRows(3),
            ]
        );
        if (null !== $emailsSentField) {
            $config = $emailsSentField->getConfig();
            $config->removeComponentsByType(GridFieldAddExistingAutocompleter::class);
            $fields->addFieldToTab(
                'Root.Sent',
                $emailsSentField
            );
        }
        $records = $this->CurrentRecords();
        if ($records && ! $this->Disable) {
            $fields->addFieldsToTab(
                'Root.Review',
                [
                    GridField::create(
                        'CurrentRecords',
                        'Today we are sending to ...',
                        $records
                    ),
                    LiteralField::create(
                        'SampleSelectStatement',
                        '<h3>Here is a sample statement used to select records:</h3>
                        <pre>' . $this->whereStatementForDays() . '</pre>'
                    ),
                    // LiteralField::create(
                    //     'SampleFieldDataForRecords',
                    //     '<h3>sample of ' . $this->DateField . ' field values:</h3>
                    //     <li>' . implode('</li><li>', $this->SampleFieldDataForRecords()) . '</li>'
                    // ),
                ]
            );
        }

        return $fields;
    }

    /**
     * Test if valid classname has been set.
     */
    public function hasValidDataObject(): bool
    {
        // nothing set yet - return true
        if (! $this->DataObject) {
            return true;
        }
        return ClassInfo::exists($this->DataObject);
    }

    /**
     * Test if valid fields have been set.
     */
    public function hasValidDataObjectFields(): bool
    {
        if (! $this->hasValidDataObject()) {
            return false;
        }
        $emailFieldOptions = $this->emailFieldOptions();
        if (!empty($emailFieldOptions) && ! isset($emailFieldOptions[$this->EmailField])) {
            return false;
        }
        $dateFieldOptions = $this->dateFieldOptions();

        return empty($dateFieldOptions) || isset($dateFieldOptions[$this->DateField]);
    }

    public function getTitle(): string
    {
        $niceTitle = '[' . $this->EmailSubject . '] // send ';
        $niceTitle .= $this->IsImmediate() ? $this->BeforeAfter : $this->Days . ' days ' . $this->BeforeAfter . ' Expiration Date';

        return $this->Code . ' - ' . $this->hasValidDataObjectFields() ? $niceTitle : 'uncompleted';
    }

    /**
     * @return bool
     */
    public function hasValidFields()
    {
        if (! $this->hasValidDataObject()) {
            return false;
        }
        if (! $this->hasValidDataObjectFields()) {
            return false;
        }

        return $this->EmailFrom && $this->EmailSubject && $this->Content;
    }

    /**
     * @return bool
     */
    public function validate()
    {
        $valid = parent::validate();
        if ($this->exists()) {
            if (! $this->hasValidDataObject()) {
                $valid->addError('Please enter valid Table/Class name ("' . htmlspecialchars($this->DataObject) . '" does not exist)');
            } elseif (! $this->hasValidDataObjectFields()) {
                if(Director::isDev()) {
                    $valid->addError('Please select valid fields for both Email & Date. '.print_r($this->emailFieldOptions(), 1).print_r($this->dateFieldOptions(), 1));
                } else {
                    $valid->addError('Please select valid fields for both Email & Date.');
                }
            } elseif (! $this->hasValidFields()) {
                $valid->addError('Please fill in all fields.  Make sure not to forget the email details (from who, subject, content)');
            }
        }

        return $valid;
    }

    /**
     * @return null|EmailReminderReplacerClassInterface
     */
    public function getReplacerObject()
    {
        $mailOutObject = $this->getMailOutObject();
        if ($mailOutObject && $mailOutObject instanceof BuildTask) {
            return $mailOutObject->getReplacerObject();
        }

        return null;
    }

    /**
     * @return null|BuildTask
     */
    public function getMailOutObject()
    {
        $mailOutClass = $this->Config()->get('mail_out_class');
        if (class_exists($mailOutClass)) {
            $obj = Injector::inst()->get($mailOutClass);
            if ($obj instanceof EmailReminderMailOutInterface) {
                return $obj;
            }
            user_error($mailOutClass . ' needs to be an instance of a Scheduled Task');
        }

        return null;
    }

    /**
     * @param int $limit
     */
    public function SampleFieldDataForRecords(?int $limit = 200): array
    {
        if ($this->hasValidFields()) {
            $className = $this->DataObject;

            /**
             * ### @@@@ START REPLACEMENT @@@@ ###
             * WHY: this throws an errror in SS4.
             */
            $objects = $className::get()->sort('RAND()')
                ->where('"' . $this->DateField . '" IS NOT NULL AND "' . $this->DateField . '" <> \'\' AND "' . $this->DateField . '" <> 0')
                ->limit($limit)
            ;
            if ($objects->count()) {
                return array_unique($objects->column($this->DateField));
            }
        }

        return [];
    }

    public function CMSEditLink()
    {
        $controller = Injector::inst()->get(EmailReminderModelAdmin::class);

        return $controller->Link() . Sanitiser::sanitise($this->ClassName) . '/EditForm/field/' . Sanitiser::sanitise($this->ClassName) . '/item/' . $this->ID . '/edit';
    }

    /**
     * @return null|DataList
     */
    public function CurrentRecords()
    {
        if ($this->hasValidFields()) {
            $className = $this->DataObject;
            $hasExcludeMethod = false;
            $excludedRecords = [];
            $hasIncludeMethod = false;
            $includedRecords = [];
            // Use StartsWith to match Date and DateTime fields
            $records = $className::get()->where($this->whereStatementForDays());
            //sample record
            $firstRecord = $records->first();
            if ($firstRecord && $firstRecord->exists()) {
                //methods
                $includeMethod = $this->Config()->get('include_method');
                $excludeMethod = $this->Config()->get('exclude_method');

                //included method?
                if ($firstRecord->hasMethod($includeMethod)) {
                    $includedRecords = [0 => 0];
                    $hasIncludeMethod = true;
                }

                //excluded method?
                if ($firstRecord->hasMethod($excludeMethod)) {
                    $excludedRecords = [0 => 0];
                    $hasExcludeMethod = true;
                }

                //see who is in and out
                if ($hasIncludeMethod || $hasExcludeMethod) {
                    foreach ($records as $record) {
                        if ($hasIncludeMethod) {
                            $in = $record->{$includeMethod}($this, $records);
                            if (true === $in) {
                                $includedRecords[$record->ID] = $record->ID;
                            }
                        }
                        if ($hasExcludeMethod) {
                            $out = $record->{$excludeMethod}($this, $records);
                            if (true === $out) {
                                $excludedRecords[$record->ID] = $record->ID;
                            }
                        }
                    }
                }

                //apply inclusions and exclusions
                if ($hasIncludeMethod && count($includedRecords)) {
                    $records = $className::get()->filter(['ID' => $includedRecords]);
                }
                if ($hasExcludeMethod && count($excludedRecords)) {
                    $records = $records->exclude(['ID' => $excludedRecords]);
                }
            }

            return $records;
        }

        return null;
    }

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if ($this->RepeatDays < ($this->Days * 3)) {
            $this->RepeatDays = $this->Days * 3;
        }

        if ($this->IsImmediate()) {
            $this->Days = 0;
        }
        if (! $this->Code) {
            $this->Code = $this->i18n_singular_name() . ' #' . $this->ID;
        }
    }

    protected function onAfterWrite()
    {
        parent::onAfterWrite();
        if ($this->SendTestTo) {
            $mailOutObject = $this->getMailOutObject();
            if ($mailOutObject && $mailOutObject instanceof BuildTask) {
                $mailOutObject->setTestOnly(true);
                $mailOutObject->setVerbose(true);
                $mailOutObject->run(null);
            }
        }
    }

    /**
     * @return array
     */
    protected function dataObjectOptions()
    {
        $subClasses = ClassInfo::subclassesFor(DataObject::class, false);

        return array_combine(array_values($subClasses), array_values($subClasses));
    }

    /**
     * @return array
     */
    protected function emailFieldOptions()
    {
        return $this->getFieldsFromDataObject(['Varchar', 'Email']);
    }

    /**
     * @return array
     */
    protected function dateFieldOptions()
    {
        return $this->getFieldsFromDataObject(['Date']);
    }

    /**
     * list of database fields available.
     *
     * @param array $fieldTypeMatchArray - strpos filter
     *
     * @return array
     */
    protected function getFieldsFromDataObject($fieldTypeMatchArray = [])
    {
        $array = [];
        if ($this->hasValidDataObject()) {
            $object = Injector::inst()->get($this->DataObject);

            if ($object) {
                $allOptions = $object->stat('db');
                $fieldLabels = $object->fieldLabels();
                foreach ($allOptions as $fieldName => $fieldType) {
                    foreach ($fieldTypeMatchArray as $matchString) {
                        if ((false !== strpos($fieldType, $matchString)) || '*' === $matchString) {
                            $label = isset($fieldLabels[$fieldName]) ? $fieldLabels[$fieldName] : $fieldName;
                            $array[$fieldName] = $label;
                        }
                    }
                }
            }
        }

        return $array;
    }

    /**
     * BeforeAfter = 'after'
     * Days = 3
     * GraceDays = 2
     *  -> minDays = -5 days start of day
     *  -> maxDays = -3 days end of day.
     *
     * @return string
     */
    protected function whereStatementForDays()
    {
        if ($this->hasValidFields()) {
            $sign = 'before' === $this->BeforeAfter ? '+' : '-';
            $graceDays = Config::inst()->get(EmailReminderNotificationSchedule::class, 'grace_days');

            if ('+' === $sign) {
                $minDays = $sign . ($this->Days - $graceDays) . ' days';
                $maxDays = $sign . $this->Days . ' days';
                $minDate = date('Y-m-d', strtotime($minDays)) . ' 00:00:00';
                $maxDate = date('Y-m-d', strtotime($maxDays)) . ' 23:59:59';
            } else {
                $minDays = $sign . $this->Days . ' days';
                $maxDays = $sign . ($this->Days - $graceDays) . ' days';
                //we purposely change these days around here ...
                $minDate = date('Y-m-d', strtotime($maxDays)) . ' 00:00:00';
                $maxDate = date('Y-m-d', strtotime($minDays)) . ' 23:59:59';
            }

            return '("' . $this->DateField . '" BETWEEN \'' . $minDate . "' AND '" . $maxDate . "')";
        }

        return '1 == 2';
    }
}
