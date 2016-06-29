<?php


class EmailReminder_DailyMailOut extends BuildTask
{


    /**
     *
     * @todo: https://docs.silverstripe.org/en/3.1/developer_guides/extending/injector/ implement
     * for email class to be used...
     * @param  [type] $request [description]
     * @return [type]          [description]
     */
    public function run($request)
    {
        //CRUCIAL !
        //
        Email::set_mailer(new EmailReminder_Mailer());

        $reminders = EmailReminder_NotificationSchedule::get();

        foreach ($reminders as $reminder) {
            if (!$reminder->hasValidFields()) {
                continue; // skip if task is not valid
            }

            $do = $reminder->DataObject;
            $offset_date = date('Y-m-d', strtotime('-'. $reminder->Days .' days'));

            // Use StartsWith to match Date and DateTime fields
            $records = $do::get()->filter(
                $reminder->DateField . ':StartsWith', $offset_date
            );

            if ($records) {
                foreach ($records as $record) {
                    $email_field = $reminder->EmailField;
                    if (Email::validEmailAddress($record->$email_field)) {

                        /* Parse HTML like a template, and translate any internal links */
                        $email_content = ShortcodeParser::get_active()
                            ->parse($record->renderWith(SSViewer::fromString($reminder->Content)));

                        $data = ArrayData::create(array(
                            'Content' => $email_content
                        ));

                        // $email_body = $record->renderWith(SSViewer::fromString($reminder->Content));
                        // echo $record->renderWith('Email_Reminder_Standard_Template');//$email_body;

                        $email = new Email(
                            $reminder->EmailFrom,
                            $record->$email_field,
                            $reminder->EmailSubject
                        );

                        $email->setTemplate('Email_Reminder_Standard_Template');

                        $email->populateTemplate($data);

                        // $email->send();
                        echo $email->debug();


                    }
                }
            }
        }
    }
}
