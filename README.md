# Member Reminder System

This module sends out reminders for Members (users) to renew their membership (or similar).

You can create zero to many reminders that go out to each member as customised emails.

This is done by running a daily task that checks which users are about to expire and emails them a reminder.  

You can set up as many reminders as you like - e.g. 10 days before, 5 days before, etc...

Daily task
---
 A daily task can run through all the users to check who needs a membership.
 You will need to set this up using a cron job or similar.

Screenshot Examples
---

Here are four screenshots that give you a flavour for what the module does.

![Example 1](https://raw.githubusercontent.com/sunnysideup/silverstripe-email_reminder/master/docs/en/examples/Example1.png)
![Example 2](https://raw.githubusercontent.com/sunnysideup/silverstripe-email_reminder/master/docs/en/examples/Example2.png)
![Example 3](https://raw.githubusercontent.com/sunnysideup/silverstripe-email_reminder/master/docs/en/examples/Example3.png)
![Example 4](https://raw.githubusercontent.com/sunnysideup/silverstripe-email_reminder/master/docs/en/examples/Example4.png)


Email Formatting
---
We use the emogrifier class to make sure the email looks as it was designed.
Emogrifier places CSS inline from a CSS file.  This CSS file can be set in the configs.

At the same time, we also replace a bunch of common links in the email content:
 * `[PASSWORD_REMINDER_LINK]`
 * `[LOGIN_LINK]`
 * `[DAYS_TO_EXPIRATION]`: for days past expiration it wil return the absolute integer (e.g. expired ten days ago should be -10 but is shown shown as 10).  

You can also set up zero to many custom replacers e.g.
 * `[RENEW_MEMBERSHIP_LINK]` using a custom class.


The replacement is shown with the HTML editor of the message, so that the editor knows how to use it and what to expect.

If you add `[PASSWORD_REMINDER_LINK]` to your message then this becomes: <u>`password reset`</u> (linking to the page where you can reset password)
Behind the scenes we replace the placeholder with a link link this: `<a href="Security/lostpassword">password reset</a>`.  The `password reset` phrase can be edited in the `lang*` file`

The HTML of the email can also be set in the custom template file for the email.


Slow Down and Security
---
The system will only ever send `n` mails per day (see configs).  This is to ensure that we don't accidentally spam all our users due to some sort of bug / glitch.  In the CMS, you can view the  `EmailReminder_EmailRecord` records to make sure emails are being sent correctly. These can be found under the `Security` tab in the CMS, as well as being shown with each `member`.


Inspiration / Ideas
---
 * https://github.com/silverstripe-labs/silverstripe-newsletter: how to set up email templates and how to allow the selection of available templates in the CMS.
 * https://github.com/sunnysideup/silverstripe-newsletter_emogrify: how to emogrify.
 * https://github.com/unclecheese/silverstripe-permamail: seems great - might be used in conjunction with this module?


Setting up a Cron job
 ---
The task to run is `dev/tasks/EmailReminder_DailyMailOut`. A few things to remember:
 * theme-ing may not work / work differently from command line
 * In the `EmailReminder_NotificationSchedule` you can set up test emails.
 * Not sure how to set up a cron job? Google it.
