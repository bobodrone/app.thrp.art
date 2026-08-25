---
id: TASK-4
title: >-
  Fix ApplicationReceived email: $message property is shadowed by Laravel's
  mailer
status: Done
assignee:
  - '@claude'
created_date: '2026-08-17 14:00'
updated_date: '2026-08-25 16:53'
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
- [x] #1 Sending ApplicationReceived through a real mail transport renders without throwing
- [x] #2 The rendered email body contains the applicant's message text
- [x] #3 A test renders the mailable for real (not under Mail::fake) so this class of shadowing regression is caught
- [x] #4 Every other Mailable is checked for the same reserved-name collision on $message
<!-- AC:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. Rename the collision: App\Mail\ApplicationReceived's promoted $message property becomes $applicantMessage, and emails/application-received.blade.php renders {{ $applicantMessage }}.
2. Update the one caller, App\Jobs\NotifyAdminsOfNewApplication, which passes it by name (message: -> applicantMessage:).
3. Add a doc comment on the property recording why the name is off-limits, matching the one on ContactMessageReceived, so nobody renames it back.
4. AC #1-#3: new tests/Feature/MailablesRenderTest.php sends ApplicationReceived through the real array transport (NOT Mail::fake, which records without rendering — the blind spot that hid this) and asserts the body carries the applicant's text.
5. AC #4: same file gets a reflection guard that walks every class in app/Mail and fails if any public property is named 'message', so the whole class of bug is caught rather than this one instance. Render the other mailables through the real transport too where their constructors allow it.
6. Verify by reproducing the original throw before the fix and confirming it is gone after; run the full suite and pint.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Reproduced first, on the unmodified code, to confirm the bug was still live:

    Mail::to('a@b.invalid')->send(new ApplicationReceived('Ada','a@b.invalid','hello','http://x'));
    => Illuminate\View\ViewException: htmlspecialchars(): Argument #1 ($string) must be of type
       string, Illuminate\Mail\Message given

Fix: the promoted property is now $applicantMessage, the blade echoes {{ $applicantMessage }}, and NotifyAdminsOfNewApplication passes applicantMessage:. A doc comment on the constructor records why the old name is off-limits, so it does not get renamed back. Same reproduction afterwards sends cleanly and the body carries the applicant's text.

AC #4 is covered two ways. The audit: ApplicationReceived was the only offender — AnswerNotification, NewQuestionNotification and UserRoleInvite use distinct names, ApplicationRejected and ConfirmNewEmail declare no public properties, and ContactMessageReceived (added under TASK-8) already avoided it deliberately. The durable half: test_no_mailable_declares_a_property_named_message reflects over every Mailable in app/Mail and fails with a named offender, so the next reintroduction breaks the build rather than production. Verified the guard actually bites by temporarily putting $message back — the test failed with 'App\Mail\ApplicationReceived declares a public $message...' — then reverted.

Also removed the workaround this bug had forced on tests/Feature/UiCopySaysResponderTest.php, which was skipping ApplicationReceived in its render loop with a comment pointing at this task. It renders now, so the skip is gone and that assertion covers it again.

Added a test for the production path itself, not just the mailable: NotifyAdminsOfNewApplication::handle() is run for real against an admin, unfaked, asserting the mail reaches them and carries the text. Asserting on the mailable alone would not have caught a caller left on the old parameter name.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Renamed ApplicationReceived's $message property to $applicantMessage, which stops Laravel's injected Illuminate\Mail\Message from shadowing it.

Changed
- app/Mail/ApplicationReceived.php — property renamed, with a comment recording why the old name is reserved.
- resources/views/emails/application-received.blade.php — echoes {{ $applicantMessage }}.
- app/Jobs/NotifyAdminsOfNewApplication.php — the one caller, updated to the new named argument.
- tests/Feature/MailablesRenderTest.php (new) — four tests: ApplicationReceived rendered through a real transport, the queued job run unfaked end to end, the other mailables rendered for real, and a reflection guard over app/Mail.
- tests/Feature/UiCopySaysResponderTest.php — dropped the skip this bug had forced on its render loop.

Verified
- Reproduced the ViewException on the unmodified code first, then confirmed the same send succeeds with the body carrying the applicant's text (AC #1, #2).
- The new tests render through the array transport rather than Mail::fake(), which was the blind spot that hid this — Mail::fake() records a mailable without rendering it (AC #3).
- AC #4 both ways: audited all seven mailables (ApplicationReceived was the only offender) and added test_no_mailable_declares_a_property_named_message as a standing guard. Proved the guard bites by temporarily reintroducing $message and watching it fail with the offending class named, then reverted.
- Full suite: 322 tests, 1092 assertions, all passing. vendor/bin/pint --test passed.
<!-- SECTION:FINAL_SUMMARY:END -->
