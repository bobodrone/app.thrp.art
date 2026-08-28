---
id: TASK-11
title: Show responder conditions-acceptance on the admin users page
status: Done
assignee:
  - '@claude'
created_date: '2026-08-28 06:45'
updated_date: '2026-08-28 06:48'
labels: []
dependencies:
  - TASK-10
type: feature
ordinal: 10000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The acceptance date recorded in TASK-10 is only visible in the applications inbox, which lists pending applications only — so once someone is approved, there is no way to answer 'when did this responder accept the conditions?'. Surface it on /admin/users, where responders are actually managed and where access gets revoked. Users are linked to their application by email via the existing User::applications() relation.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 Each responder row on /admin/users shows the date they accepted the conditions, when an application on record has one
- [x] #2 A responder with no acceptance on record (invited directly, or applied before the checkbox existed) is visibly flagged rather than left blank
- [x] #3 Non-responders who have never applied show nothing extra, so the table stays readable
- [x] #4 The users table does not gain an N+1 query for this
- [x] #5 Feature tests cover the accepted, not-accepted, and non-responder cases
<!-- AC:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. User model: add termsAcceptedAt(): ?Carbon reading the max terms_accepted_at off the existing applications() relation (email-keyed hasMany).
2. AdminUserManagement::render(): eager-load applications (constrained to the columns needed) so the table stays one extra query, not one per row.
3. user-management.blade.php: under the email in the first cell, show '✓ Conditions accepted 27 Aug 2026' when a date exists; for responders with none, show a muted 'No conditions acceptance on record'; nothing for anyone else.
4. Tests in AdminUserManagementTest: responder with acceptance shows the date, responder without shows the flag, member without an application shows neither. Assert the query count does not scale with row count.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Implemented as planned. User::termsAcceptedAt() reads the max terms_accepted_at off the existing email-keyed applications() relation, eager-loaded as 'applications:email,terms_accepted_at' in AdminUserManagement::render(). The flag for a missing acceptance is gated on role === UserRole::Creator rather than isCreator(), because isCreator() includes admins and the notice would then nag on every admin row — covered by its own test. Anonymised accounts lose the link (their email is scrubbed), which is expected. The N+1 guard was proved to bite: with the eager load removed the query count went 4 -> 9 for five extra responders, and the test failed as intended before being restored. php artisan test => 332 passed; pint clean.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Responder rows on /admin/users now show '✓ Conditions accepted 27 Aug 2026' from the applicant's application record, and responders with nothing on record (invited directly, or applied before the checkbox existed) show 'No conditions acceptance on record' instead of a blank. Members and admins without applications are left unmarked. Verified with five new tests in AdminUserManagementTest covering accepted, not-accepted, member, admin and query-count cases; full suite 332 passing, pint clean.
<!-- SECTION:FINAL_SUMMARY:END -->
