# Member Reminder System

This module sends out reminders for Members (users) to renew their membership (or similar).

You can create as many reminders as you see fit. They go out to each member as customised emails.

The reminders are sent by a daily task that checks which users are about to expire. 

You can set up as many reminders as you like - e.g. 10 days before, 5 days before, 5 days after membership expires, etc...


Daily task
---

A daily task can run through all the users to check whose membership is expiring.
 
You will need to set this task up using a cron job or similar.


Model
---
 * `EmailReminder_NotificationSchedule`:
   * Days before / after experiation to run
   * Template to be used for mail out.
   * Message (HTML)
   * Number of days lee-way a message can have - i.e. if the message is not
     sent exactly on the day it is supposed to then the system have up to `n` number of days to send it.
   * MANY_MANY: Members: if any Users are listed here, the task will send the reminder to these test users instead of the actual users that need to receive it. For these test users, the expiry date will be ignored (i.e. they test users will get the email every time)
   * Date field to use: date fiels are automatically selected from the `Member` class and using the `field_labels` config for Member, the meaningful name for the field is shown here (e.g. `Created`, `LastEdited`, `ExpiryDate`.  
 * `EmailReminder_EmailRecord`:
   * One for each notification for each member. This helps us ensure
     that no emails are sent twice.


Other classes
---
 * `EmailReminder_DailyMailOut`
 * `EmailReminder_Mailer` extends Mailer so that we can add change the content of the email (e.g. replace tags) before sending it.


Email Formatting
---
We use the emogrifier class to make sure the email looks as it was designed.
Emogrifier places CSS inline from a CSS file.  The location of the CSS file can be set in the configs.

At the same time, we also replace a bunch of common links in the email content:
 * `[PASSWORD_REMINDER_LINK]`
 * `[LOGIN_LINK]`
 * `[DAYS_TO_EXPIRATION]`: for days past expiration it wil return the absolute integer (e.g. expired ten days ago should be -10 but it is shown as 10).  

You can also set up custom replacers e.g.
 * `[RENEW_MEMBERSHIP_LINK]` 
using a custom class. 


The replacement is shown with the HTML editor of the message, so that the editor knows how to use it and what to expect.

If you add `[PASSWORD_REMINDER_LINK]` to your message then this becomes: <u>`password reset`</u> (linking to the page where you can reset password)
Behind the scenes we replace the placeholder with a link link this: `<a href="Security/lostpassword">password reset</a>`.  The `password reset` phrase can be edited in the `lang*` files.

The HTML of the email can also be set in the custom template file for the email.

Slow Down and Security
---
The system will only ever send `n` mails per day (see configs).  This is to ensure that we don't accidentally spam all our users due to some sort of bug / glitch.  In the CMS, you can view the  `EmailReminder_EmailRecord` records to make sure emails are being sent correctly. These can be found under the `Security` tab in the CMS, and relevant records are also shown with each `member`.


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
