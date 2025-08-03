<?php

namespace SunnySideUp\EmailReminder\Model;

use SilverStripe\Control\Director;
use SilverStripe\Control\Email\Email;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
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
use SilverStripe\Security\Permission;
use SunnySideUp\EmailReminder\Api\EmailReminderMailOut;
use SunnySideUp\EmailReminder\Cms\EmailReminderModelAdmin;
use SunnySideUp\EmailReminder\Interfaces\EmailReminderMailOutInterface;
use SunnySideUp\EmailReminder\Interfaces\EmailReminderReplacerClassInterface;
use Sunnysideup\SanitiseClassName\Sanitiser;

/**
 * Class \SunnySideUp\EmailReminder\Model\EmailReminderNotificationSchedule
 *
 * @property string $Code
 * @property string $DataObject
 * @property string $EmailField
 * @property string $CarbonCopyMethod
 * @property string $BlindCarbonCopyMethod
 * @property string $BeforeAfter
 * @property string $DateField
 * @property int $Days
 * @property int $RepeatDays
 * @property string $EmailFrom
 * @property string $EmailSubject
 * @property string $Content
 * @property bool $Disable
 * @property string $SendTestTo
 * @method DataList|EmailReminderEmailRecord[] EmailsSent()
 */
class EmailReminderNotificationSchedule extends DataObject
{
    /**
     * @var array<string, string>
     */
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
        'Email',
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
    private static $disabled_checkbox_label = 'Do not include in daily mailouts';

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
        'Code' => 'Varchar(64)',
        'DataObject' => 'Varchar(255)',
        'EmailField' => 'Varchar(100)',
        'CarbonCopyMethod' => 'Varchar(100)',
        'BlindCarbonCopyMethod' => 'Varchar(100)',
        // days stuff
        'BeforeAfter' => "Enum('before,after,immediately','before')",
        'DateField' => 'Varchar(100)',
        'Days' => 'Int',
        'RepeatDays' => 'Int',

        // email stuff
        'EmailFrom' => 'Varchar(100)',
        'EmailSubject' => 'Varchar(100)',
        'Content' => 'HTMLText',

        //  other
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

    /**
     * @param DataObject|string $recordOrEmail
     * @param bool              $isTestOnly    optional
     * @param bool              $force         optional
     */
    public function sendOne($recordOrEmail, ?bool $isTestOnly = false, ?bool $force = false): bool
    {
        $mailerClassName = Config::inst()->get(EmailReminderNotificationSchedule::class, 'mail_out_class');
        $mailer = Injector::inst()->get($mailerClassName);

        return $mailer->send($this, $recordOrEmail, $isTestOnly, $force);
    }

    public function IsImmediate(): bool
    {
        return 'immediately' === $this->BeforeAfter;
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

        if ($this->IsImmediate()) {
            $fields->removeByName(['DateField', 'Days', 'RepeatDays']);
        } else {
            $fields->addFieldsToTab(
                'Root.Main',
                [
                    $dateFieldField = DropdownField::create(
                        'DateField',
                        'Date Field',
                        $this->dateFieldOptions()
                    )
                        ->setDescription('Select a valid Date field to calculate when reminders should be sent')
                        ->setEmptyString('[ Please select ]'),

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
                                <br />In this field you can indicate for how long we will apply this rule.
                                <br />If set to set to zero, no reminders will be sent.
                                '
                            )
                        )->setScale(0),
                ]
            );

            if ($this->Config()->get('default_date_field')) {
                $fields->replaceField('DateField', $dateFieldField->performReadonlyTransformation());
            }
        }
        $fields->addFieldsToTab(
            'Root.EmailContent',
            [
                TextField::create('EmailFrom', 'Email From Address')
                    ->setDescription('The email from address, eg: "My Company info@example.com"'),
                $subjectField = TextField::create('EmailSubject', 'Email Subject Line')
                    ->setDescription('The subject of the email'),
                $contentField = HTMLEditorField::create('Content', 'Email Content'),
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

            $html .= '</ul>';
            $subjectField->setDescription('for replacement options, please see below ...');
            $contentField->setDescription(
                DBField::create_field(
                    'HTMLText',
                    $html
                )
            );
        }

        $fields->addFieldsToTab(
            'Root.Test',
            [
                TextareaField::create('SendTestTo', 'Send test email to ...')
                    ->setDescription(
                        '
                        Separate emails by commas,
                        a test email will be sent every time you <strong>save this Email Reminder</strong>,
                        if you do not want test emails to be sent make sure this field is empty
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

        $fields->addFieldsToTab(
            'Root.Advanced',
            [
                ReadonlyField::create('Code')
                    ->setDescription('Used to uniquely identiy this record.'),
            ]
        );
        $disableLabel = $this->Config()->get('disabled_checkbox_label');
        $fields->addFieldToTab(
            'Root.Advanced',
            CheckboxField::create('Disable', $disableLabel)
                ->setDescription('If checked this email will not be sent during the daily mail out, instead it will be sent after an event like a form submission.')
        );
        $whatIsThis = 'ERROR';
        $obj = Injector::inst()->get($this->DataObject);
        if ($obj) {
            $whatIsThis = $obj->i18n_singular_name();
        }

        $fields->addFieldToTab(
            'Root.Advanced',
            $dataObjectField = DropdownField::create(
                'DataObject',
                'Works on ... ',
                $this->dataObjectOptions()
            )
                ->setDescription('This is a : ' . $whatIsThis)
        );
        if ($this->Config()->get('default_data_object')) {
            $fields->replaceField('DataObject', ReadonlyField::create('DataObjectNice', 'Works on ...', $whatIsThis));
        }

        $fields->addFieldsToTab(
            'Root.Advanced',
            [
                $emailFieldField = DropdownField::create(
                    'EmailField',
                    'Email Field',
                    $this->emailFieldOptions()
                )
                    ->setDescription('Select the field that will contain a valid email address')
                    ->setEmptyString('[ Please select ]'),
                ReadonlyField::create(
                    'CarbonCopyMethod',
                    'Carbon Copy (CC) method',
                    $this->CarbonCopyMethod
                )
                    ->setDescription('This is set by your developer'),
                ReadonlyField::create(
                    'BlindCarbonCopyMethod',
                    'Blind Carbon Copy (BCC) method',
                    $this->BlindCarbonCopyMethod
                )
                    ->setDescription('This is set by your developer'),

            ]
        );
        if ($this->Config()->get('default_email_field')) {
            $fields->replaceField('EmailField', $emailFieldField->performReadonlyTransformation());
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
        if (! empty($emailFieldOptions) && ! isset($emailFieldOptions[$this->EmailField])) {
            return false;
        }

        if (false === $this->IsImmediate()) {
            $dateFieldOptions = $this->dateFieldOptions();
            if (! empty($dateFieldOptions) && ! isset($dateFieldOptions[$this->DateField])) {
                return false;
            }
        }

        return true;
    }

    public function getTitle(): string
    {
        $niceTitle = $this->EmailSubject . ' // send ';
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

    public function validate()
    {
        $valid = parent::validate();
        if ($this->exists()) {
            if (! $this->hasValidDataObject()) {
                $valid->addError('Please enter valid Table/Class name ("' . htmlspecialchars($this->DataObject) . '" does not exist)');
            } elseif (! $this->hasValidDataObjectFields()) {
                if (Director::isDev()) {
                    $valid->addError('Please select valid fields for both Email & Date. ' . print_r($this->emailFieldOptions(), 1) . print_r($this->dateFieldOptions(), 1));
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
        if ($mailOutObject) {
            return $mailOutObject->getReplacerObject();
        }

        return null;
    }

    /**
     * @return null|EmailReminderMailOutInterface
     */
    public function getMailOutObject()
    {
        $mailOutClass = $this->Config()->get('mail_out_class');
        if (class_exists($mailOutClass)) {
            $obj = Injector::inst()->get($mailOutClass);
            if ($obj instanceof EmailReminderMailOutInterface) {
                return $obj;
            }

            user_error($mailOutClass . ' needs to be an instance of a EmailReminderMailOutInterface');
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

            $objects = $className::get()->sort('RAND()')
                ->where('"' . $this->DateField . '" IS NOT NULL AND "' . $this->DateField . '" <> \'\' AND "' . $this->DateField . '" <> 0')
                ->limit($limit);
            if ($objects->count()) {
                return array_unique($objects->column($this->DateField));
            }
        }

        return [];
    }

    public function CMSEditLink()
    {
        $controller = Injector::inst()->get(EmailReminderModelAdmin::class);

        return $controller->Link() . '/' . Sanitiser::sanitise($this->ClassName) . '/EditForm/field/' . Sanitiser::sanitise($this->ClassName) . '/item/' . $this->ID . '/edit';
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
            $this->Code = md5($this->ClassName . '_' . $this->ID);
        }

        if ($this->SendTestTo) {
            $mailOutObject = $this->getMailOutObject();
            $emails = array_filter(explode(', ', $this->SendTestTo));
            foreach ($emails as $email) {
                $email = trim($email);
                $mailOutObject->send($this, $email, true, true);
            }
        }
    }

    protected function onAfterWrite()
    {
        parent::onAfterWrite();
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
        return $this->getFieldsFromDataObject(['Date', 'DBDatetime']);
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
                $allOptions = $object->config()->get('db');
                $allOptions['Created'] = 'DBDatetime';
                $allOptions['LastEdited'] = 'DBDatetime';
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
        if ($this->DateField && $this->hasValidFields()) {
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
                $minDate = date('Y-m-d', strtotime($minDays)) . ' 00:00:00';
                $maxDate = date('Y-m-d', strtotime($maxDays)) . ' 23:59:59';
            }

            return '("' . $this->DateField . '" BETWEEN \'' . $minDate . "' AND '" . $maxDate . "')";
        }

        return '1 = 2';
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::check(EmailReminderModelAdmin::PERMISSION_PROVIDER_CODE, 'any', $member);
    }

    public function canView($member = null)
    {
        return Permission::check(EmailReminderModelAdmin::PERMISSION_PROVIDER_CODE, 'any', $member);
    }

    public function canEdit($member = null)
    {
        return Permission::check(EmailReminderModelAdmin::PERMISSION_PROVIDER_CODE, 'any', $member);
    }


    public function canDelete($member = null)
    {
        if ($this->EmailsSent()->exists()) {
            return false;
        }
        return Permission::check(EmailReminderModelAdmin::PERMISSION_PROVIDER_CODE, 'any', $member);
    }
}
