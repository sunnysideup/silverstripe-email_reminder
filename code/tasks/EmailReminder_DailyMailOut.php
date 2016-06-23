<?php


class EmailReminder_DailyMailOut extends DailyTask
{


    /**
     *
     * @todo: https://docs.silverstripe.org/en/3.1/developer_guides/extending/injector/ implement
     * for email class to be used...
     * @param  [type] $request [description]
     * @return [type]          [description]
     */
    function run($request)
    {
        //CRUCIAL !
        //
        Email::set_mailer(new EmailReminder_Mailer());
    }

}
