---
id: TASK-8
title: Add a contact form for all visitors/users to send a message to THRP
status: Done
assignee:
  - '@claude'
created_date: '2026-08-25 16:27'
updated_date: '2026-08-25 16:43'
labels: []
dependencies: []
ordinal: 8000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Maybe to all admins? or maybe only to a fixed email? The form need to be somehow protected against spam since it will be public. please suggest a solution that does not cost any money or require complicated integration on our end.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 A public /contact page renders for guests and logged-in users, with name, email, subject and message fields
- [x] #2 Submissions are stored in a contact_messages table and delivered by email to config('contact.to') when set, falling back to all admin users
- [x] #3 Spam protection: hidden honeypot field, minimum fill-in time, and per-IP rate limiting — no paid service or third-party integration
- [x] #4 Logged-in users get their name and email prefilled, and the stored message is linked to their user account
- [x] #5 Admins can read and mark messages handled at /admin/messages, reachable from the admin navigation
- [x] #6 Contact link is discoverable from the site navigation
- [x] #7 Feature tests cover the happy path, validation, each spam guard, and the admin inbox
<!-- AC:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. config/contact.php + .env.example keys: CONTACT_TO (nullable), throttle limits, honeypot/min-time settings.
2. Migration create_contact_messages_table: id, user_id (nullable, nullOnDelete), name, email, subject, message, ip_hash, handled_at, timestamps; index on handled_at.
3. App\Models\ContactMessage + ContactMessageFactory.
4. App\Mail\ContactMessageReceived mailable + resources/views/emails/contact-message.blade.php, styled like emails/application-received. Reply-To set to the sender so admins can answer directly. Avoid the $message property collision that TASK-4 documents on ApplicationReceived — name the property $body.
5. App\Jobs\NotifyAdminsOfContactMessage (ShouldQueue): resolve recipients from config('contact.to') else all admins; send mailable per recipient.
6. App\Livewire\ContactForm: fields name/email/subject/message + honeypot $website + $startedAt timestamp set in mount(). submit() runs, in order, honeypot check (silently pretend success), time-trap, RateLimiter::tooManyAttempts keyed on hashed IP, then validate, persist, dispatch job. Prefill from auth()->user() in mount().
7. resources/views/livewire/contact/form.blade.php — hero + card layout copied from livewire/apply/form.blade.php so it matches the site.
8. App\Livewire\AdminContactMessages + resources/views/livewire/admin/contact-messages.blade.php: list newest first, unhandled first, mark handled/unhandled, delete.
9. Routes: GET /contact -> ContactForm (public, named contact); GET /admin/messages -> AdminContactMessages inside the existing admin group.
10. Navigation: add Contact to desktop + mobile public links; add Messages to the admin $navLinks with an unhandled count badge.
11. tests/Feature/ContactFormTest.php and tests/Feature/AdminContactMessagesTest.php.
12. Run vendor/bin/pint and the full test suite.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Spam protection (the open question in the task): three free, self-contained layers, no CAPTCHA account and no third-party script — so nothing to pay for and nothing to integrate.

1. Honeypot — a hidden 'website' input, off-screen, tabindex=-1, aria-hidden, autocomplete=off. Anything in it means a bot, and the bot is shown the success screen so whoever runs it learns nothing. Field name is CONTACT_HONEYPOT_FIELD, so it can be renamed if spam adapts to it.
2. Time trap — CONTACT_MIN_SECONDS (default 3). The start time is a Livewire #[Locked] property, so the page cannot post back an older timestamp to walk past it; a test asserts the exception.
3. Per-IP rate limit — CONTACT_MAX_PER_HOUR (3) and CONTACT_MAX_PER_DAY (10) on Laravel's RateLimiter, which runs on the database cache store already configured. Counters only increment on a stored message, so someone who keeps tripping validation does not burn their quota.

Delivery: config('contact.to') (CONTACT_TO, comma-separated) wins; with it empty every non-blocked admin receives the mail instead, so an unset env var means 'too much mail' rather than 'no mail'. Reply-To is the sender, so an admin can just hit reply.

Privacy: only a sha256 of the IP is stored, never the raw address — enough to spot one source flooding the form without keeping an identifiable IP for every visitor who writes in.

Storage: contact_messages rows survive mail failures and give the whole admin team one inbox with a handled flag, so two admins do not answer the same message. user_id is nullOnDelete — a deleted account does not take its message with it.

TASK-4 note: ContactMessageReceived deliberately calls its text property $body, not $message, because Laravel's mailer injects its own $message into every mail view. TASK-4 itself is untouched (out of scope), but the new mailable does not repeat the bug and has a test that renders it through a real transport rather than under Mail::fake().
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Built a public contact form at /contact, an admin inbox at /admin/messages, and email delivery in between.

What changed
- config/contact.php + .env.example keys (CONTACT_TO, CONTACT_HONEYPOT_FIELD, CONTACT_MIN_SECONDS, CONTACT_MAX_PER_HOUR, CONTACT_MAX_PER_DAY).
- Migration create_contact_messages_table, App\Models\ContactMessage and its factory.
- App\Livewire\ContactForm + resources/views/livewire/contact/form.blade.php — public page, styled to match /apply, prefilled for signed-in users.
- App\Mail\ContactMessageReceived + emails/contact-message.blade.php, dispatched via App\Jobs\NotifyAdminsOfContactMessage.
- App\Livewire\AdminContactMessages + its blade — search, unhandled-only filter, expandable body, mark handled/reopen, delete, mailto reply.
- Routes for both pages; navigation gains a public Contact link and, for admins, a Messages link with an open-message count badge.

Decisions the task left open: messages go to CONTACT_TO when set and to every non-blocked admin otherwise; spam is stopped by a honeypot, a time trap and a per-IP rate limit — all free, no third-party service, nothing to integrate.

Verification
- Full suite: 318 tests, 1071 assertions, all passing (php artisan test). 31 of those are new: tests/Feature/ContactFormTest.php (18) and tests/Feature/AdminContactMessagesTest.php (13), covering the happy path for guests and members, validation, each of the three spam guards separately, the locked-timestamp tamper case, both delivery routes, and every inbox action.
- The notification email is rendered through a real transport (not Mail::fake) and asserted on subject, body and Reply-To — the blind spot TASK-4 records.
- vendor/bin/pint --test: passed.
- php artisan migrate --pretend against MySQL, then migrated the local dev database.
- Live check against php artisan serve: / returns 200 and carries the /contact link, /contact returns 200 with the honeypot and all four fields present, /admin/messages returns 302 to login for a guest. npm run build re-run so the new Tailwind classes are compiled.
<!-- SECTION:FINAL_SUMMARY:END -->
