---
id: TASK-7
title: >-
  We need a way to block question users if they spam or post inappropriate
  information
status: Done
assignee:
  - '@claude'
created_date: '2026-08-25 15:20'
updated_date: '2026-08-25 16:25'
labels: []
dependencies: []
ordinal: 7000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
We might need a simple 'normal' users admin page that only admins can access and edit the users by blocking them for example. maybe then we also need a small text field with a reason for the blocking?
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 Admins can block a user from an admin table, with an optional reason of up to 1000 characters
- [x] #2 A blocked user cannot sign in, and an already-open session is terminated on their next request
- [x] #3 The blocking reason is shown to the blocked user when they attempt to sign in
- [x] #4 Unblocking restores full access and clears blocked_at, blocked_by and blocked_reason
- [x] #5 /admin/users lists every user regardless of role, searchable by name or email and filterable by role
- [x] #6 Admins can change a user's role and anonymise a user from that table, without destroying their questions or the answers written on them
- [x] #7 An admin cannot block or demote themselves, and the last remaining admin cannot be blocked or demoted
- [x] #8 The responder application inbox remains reachable and functional after the split
- [x] #9 Only admins can reach the users admin page; everyone else gets 403
<!-- AC:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
Decisions taken with the user (2026-08-25): block = no sign-in at all; reason is shown to the blocked user; delete = anonymise, never a cascading hard delete; and the admin surface is re-split into "Users" (everyone) + "Applications" (the responder inbox) rather than gaining a fourth page.

**1. Schema — `users` gets the moderation triple, mirroring `hidden_*` on questions (task-6)**
Migration `add_blocked_to_users_table`: `blocked_at` (timestamp, nullable), `blocked_by` (foreignId nullable, constrained users, nullOnDelete), `blocked_reason` (text nullable), index on `blocked_at`. Deliberately off the `role` axis: a blocked responder is still a responder, and unblocking must put them back exactly where they were.

**2. `User` model**
`isBlocked()`, `block(User $admin, ?string $reason)`, `unblock()` (clears all three columns), casts `blocked_at => datetime`, scopes `blocked()` / `notBlocked()`. Same shape as `Question::hide()` / `unhide()`.

**3. Enforcement — two points, both required**
- `LoginRequest::authenticate()`: after a successful `Auth::attempt`, if the user is blocked, log back out and throw a `ValidationException` on `email` carrying the reason. Do not leak block state on a *wrong* password.
- New `EnsureUserIsNotBlocked` middleware, aliased in `bootstrap/app.php` and added to the `auth` group: logs out, invalidates the session, redirects to login with the reason. Without this, blocking someone mid-spam has no effect until they log out, and remember-me cookies keep working.
- `block()` also deletes the user's rows from the `sessions` table (driver is `database`) so the kill is immediate rather than next-request.
- Guest-side gap to close: `PasswordResetLinkController` should refuse a blocked address, otherwise a reset mail is still sent.

**4. `/admin/users` becomes the Users table (`AdminUserManagement`, retitled)**
- Paginated table of every user: name, email, role badge, joined, questions-asked count, blocked badge + reason.
- `#[Url]` search (name/email) and role filter, plus a `blockedOnly` toggle — same filter idiom as `AdminQuestionsTable`.
- Invite box gains a role dropdown, absorbing the responder invite form; both already call `UserInviter::invite()`.
- Row actions: change role (absorbs both existing `revoke()` methods, keeping the self-revoke and last-admin guards), block/unblock, edit name, anonymise.
- Block opens a modal with an optional reason, `max:1000`, whitespace-only stored as null — same contract as `confirmHide`/`hide`.
- Anonymise: scrub name/email/avatar/bio/social links, keep the row and the questions. Never `delete()` — `questions.asked_by` is `cascadeOnDelete`, so a hard delete would silently destroy the asker's questions *and* the responders' answers on them.
- Guards: an admin cannot block, demote or anonymise themselves, and the last remaining admin cannot be blocked.

**5. `/admin/responders` becomes the applications inbox**
`AdminCreatorManagement` keeps only approve/reject of pending `CreatorApplication`s; its invite form and responder list move to the Users page. Route renamed to `/admin/applications` with a 301 from `/admin/responders`, following the existing legacy-redirect block in `routes/web.php`.

**6. Navigation**
`layouts/navigation.blade.php` admin links become Questions / Users / Applications. Cross-links in the three admin blades updated to match.

**7. Login UI**
`auth/login.blade.php` renders the block notice with its reason, written for the blocked person rather than as an internal note.

**8. Tests**
- New `BlockUserTest`: block with/without reason, whitespace-only reason, over-long reason rejected, unblock clears all three columns, blocked user cannot log in, an open session dies on the next request, blocked user's reason appears at login, self-block and last-admin guards, blocking sends no mail.
- New `AnonymiseUserTest`: questions and answers survive, PII is gone, the user cannot log in afterwards.
- Rewrite `AdminUserManagementTest` for the new table (search, role filter, role change, invite-with-role) and trim `AdminCreatorManagementTest` to the application inbox.
- Non-admins get 403 on both pages.

**Known limits to state on delivery:** a blocked person can still register a fresh account under a different email — this is moderation, not identity enforcement. Blocking does not touch the person's existing questions; task-6's hide tool remains the way to take content down.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
**Two things the plan did not anticipate, both found by the tests**

1. `#[Fillable]` on the User model does not list the moderation columns, so `update()` silently dropped every write from `block()` and `unblock()`. They use `forceFill()->save()` instead, which is the right end state anyway: no request payload can reach `blocked_at` / `blocked_by` / `blocked_reason` by mass assignment. `anonymise()` was already written that way.

2. `actingAs()` cannot prove that an open session dies, because it hands the guard an in-memory model that never re-reads the row. `test_an_open_session_dies_on_the_next_request` signs in for real over `POST /login` and calls `Auth::guard('web')->forgetUser()` to force the fresh resolve a genuine next request performs. Sessions are `array` under PHPUnit, so the session-row deletion is covered separately by `test_blocking_deletes_the_persons_stored_sessions`, which flips `session.driver` to `database`.

**Two additions beyond the written plan**, both needed for "blocked" to mean anything:
- `User::scopeCreators()` now excludes blocked accounts, and `PublicCreatorController` 404s on them — otherwise a blocked responder kept a live profile on the public directory.
- `PasswordResetLinkController` refuses a blocked address, which the plan flagged as a gap and is now closed.

**Renames** beyond the route change: `AdminCreatorManagement` → `AdminApplications`, its view to `livewire/admin/applications.blade.php`, its flash key to `admin-applications-ok`, and its test file to `AdminApplicationsTest`. The old class name described a job it no longer does. `/admin/responders` and `/admin/creators` both 301 to `/admin/applications`.

**Role changes are driven from Alpine** (`x-on:change="$wire.changeRole(...)"`) rather than `wire:change`, so the `$event.target.value` argument is unambiguously available.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Blocking added as the moderation triple on users (blocked_at / blocked_by / blocked_reason), mirroring how hiding works on questions. A block is refused at sign-in, kills the person's stored sessions, drops them from the public responder directory, and blocks their password-reset route; the reason they wrote is what the blocked person reads on the login screen. Deleting is offered as anonymise instead, because questions.asked_by cascades and a real delete would take the asker's questions and the responders' answers with it.

The three admin pages were re-split rather than added to: /admin/users is now one table of every account (search, role filter, invite with role, role change, rename, block, anonymise), absorbing both former invite forms and both revoke methods; /admin/responders became /admin/applications holding only the approve/reject queue, with 301s from both old URLs.

Verified by the full suite: 287 tests, 969 assertions, all passing (php artisan test), plus vendor/bin/pint --test and npm run build. Each acceptance criterion is covered by a named test in BlockUserTest, AnonymiseUserTest, AdminUserManagementTest or AdminApplicationsTest.
<!-- SECTION:FINAL_SUMMARY:END -->
