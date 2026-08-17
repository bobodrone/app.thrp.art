---
id: TASK-4
title: >-
  Fix ApplicationReceived email: $message property is shadowed by Laravel's
  mailer
status: To Do
assignee: []
created_date: '2026-08-17 14:00'
labels: []
dependencies: []
priority: high
type: bug
ordinal: 4000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The admin notification for a new responder application fails to send. App\Mail\ApplicationReceived declares a public string $message property, and resources/views/emails/application-received.blade.php renders it as {{ $message }}. Laravel's Mailer injects its own $message variable (an Illuminate\Mail\Message instance) into every mail view, which shadows the Mailable's property, so the blade tries to echo an object.

Reproduced against the array mail driver:

    Mail::to('a@b.c')->send(new ApplicationReceived('Ada','a@b.c','hello','http://x'));
    => Illuminate\View\ViewException: htmlspecialchars(): Argument #1 ($string) must be of type string,
       Illuminate\Mail\Message given (View: resources/views/emails/application-received.blade.php)

The failure is invisible in the existing test suite because App\Jobs\NotifyAdminsOfNewApplication is only ever exercised under Mail::fake(), which records the mailable without rendering it. Consequence in production: an admin is never told that an application arrived, and the job fails.

The fix is to stop using the reserved name — rename the property and the blade variable to something like $applicantMessage, or pass it through Content(with: [...]) under a non-colliding key. Found while renaming creator->responder (TASK-3); it predates that work and is unrelated to it.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [ ] #1 Sending ApplicationReceived through a real mail transport renders without throwing
- [ ] #2 The rendered email body contains the applicant's message text
- [ ] #3 A test renders the mailable for real (not under Mail::fake) so this class of shadowing regression is caught
- [ ] #4 Every other Mailable is checked for the same reserved-name collision on $message
<!-- AC:END -->
