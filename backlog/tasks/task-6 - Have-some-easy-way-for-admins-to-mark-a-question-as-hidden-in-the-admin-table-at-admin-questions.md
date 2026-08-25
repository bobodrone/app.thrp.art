---
id: TASK-6
title: >-
  Have some easy way for admins to mark a question as 'hidden' in the admin
  table at: /admin/questions
status: Done
assignee:
  - '@claude'
created_date: '2026-08-25 15:18'
updated_date: '2026-08-25 15:49'
labels: []
dependencies: []
ordinal: 6000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Admins need a way to take a question out of public view without deleting it, and to say why.

Hiding is a moderation axis orthogonal to the asked/claimed/answered lifecycle, so it is represented by a nullable `hidden_at` timestamp (plus `hidden_by` and an optional `hidden_reason`) rather than a new QuestionStatus case — mirroring how `deleted_at` already sits beside `status`.

Decided with the user:
- Hide and Delete stay two distinct tools. Delete remains the silent, total option (spam/junk). Hide is the honest one.
- A hidden question leaves the public feed, the public detail page, the responder open/answered lists, and public answer counts.
- The asker still sees their own hidden question on /my-questions and on its detail page, badged Hidden, with the admin's reason shown when one was given.
- No email. Disclosure is in-app only — moderation mail invites argument and cannot be un-sent. The existing Mailable+Job pattern makes adding email later a non-schema change.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 A `hidden_at` / `hidden_by` / `hidden_reason` migration exists and Question exposes hide(), unhide(), isHidden() and a visible() scope
- [x] #2 An admin can hide a question from /admin/questions, optionally supplying a reason, and can unhide it again
- [x] #3 The admin table badges hidden questions and can filter to show only them
- [x] #4 A hidden question is absent from the public feed, returns 404 on its public detail page for non-askers, and is absent from the responder open and answered lists
- [x] #5 A hidden question cannot be claimed or answered, including via a direct POST
- [x] #6 A hidden question does not count toward a responder's public answer count
- [x] #7 The asker sees their hidden question on /my-questions and its detail page, badged Hidden and showing the reason when one was set
- [x] #8 No email is sent when a question is hidden
- [x] #9 Feature tests cover the admin hide/unhide actions, every public-visibility exclusion, and the asker-facing notice
<!-- AC:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. Migration: add hidden_at (timestamp, nullable, indexed), hidden_by (nullable FK users, nullOnDelete) and hidden_reason (text, nullable) to questions.
2. Question model: cast hidden_at; add isHidden(), hide(User $admin, ?string $reason), unhide(), hiddenBy() relation, and scopeVisible(). Guard isClaimableBy() and isAnswerableBy() on hidden_at so the claim/answer POST paths refuse a hidden question.
3. Apply visible() to the public surfaces: HomeController feed, CreatorDashboard open list, CreatorAnsweredController, and the publiclyCredited answer counts used by CreatorsIndex and PublicCreatorController.
4. QuestionController@show: 404 a hidden question unless the viewer is its asker or an admin; pass the hidden notice to the view.
5. Admin table (AdminQuestionsTable + questions-table.blade.php): Hide action opening a reason modal, Unhide action, a Hidden badge stacked in the status column, and a 'Hidden only' filter checkbox alongside 'Show deleted'.
6. Asker-facing UI: Hidden badge and reason panel on my-questions rows and on questions/show; suppress the claim/answer blocks there.
7. Tests: extend AdminQuestionsTableTest for hide/unhide/reason/filter, and add a HideQuestionTest covering each public exclusion, the claim guard, the answer count, and the asker notice.
8. Run the full suite and Pint.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Represented hiding as `questions.hidden_at` / `hidden_by` / `hidden_reason` rather than a fourth QuestionStatus case. Status is the lifecycle axis (asked → claimed → answered) and hiding is orthogonal to it: folding them together would lose the lifecycle position and leave unhide with nothing to restore to, and would break every existing where('status', …) scope plus the admin status filter. The nullable-timestamp shape also matches the two moderation flags already on the model (deleted_at, and the answer soft-delete). hidden_by adds an audit trail the existing soft-deletes lack.

hide()/unhide() use forceFill and the columns are deliberately left out of $fillable — moderation state should not be reachable by mass assignment from the admin edit form. The QuestionFactory hidden() state goes through hide() for the same reason.

The claim and publish guards live in the WHERE clause of claimBy() and publishPrimaryAnswerFrom(), not only in isClaimableBy()/isAnswerableBy(). CreatorDashboard::claim, CreatorQuestionDetail::claim and QuestionClaimController all call claimBy() directly, so a page left open from before the hide would otherwise still win the claim.

Answer::scopePubliclyCredited now filters on whereHas('question', visible()). Side effect worth noting: because the relation carries Question's SoftDeletes global scope, answers on soft-deleted questions stop counting toward a responder's public total too. That was arguably already a bug and the new behaviour is the intended one, but it is a change beyond the literal AC.

unhide() clears hidden_reason along with the flag — a stale reason would otherwise resurface on the asker's page if the question were hidden again later without one.

Not run: `php artisan migrate` against the dev/production MySQL database. The migration is verified only through the SQLite test suite.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Admins can now hide a question from /admin/questions instead of deleting it, with an optional reason shown to the person who asked.

Representation: a nullable `hidden_at` timestamp on `questions`, plus `hidden_by` and `hidden_reason` — a moderation axis kept separate from the `status` lifecycle, mirroring how `deleted_at` already sits beside it. Question gains isHidden(), hide(), unhide(), isHideableBy(), isViewableBy() and a visible() scope.

Behaviour: a hidden question leaves the public feed, 404s on its public detail page, drops out of the responder open and answered lists, and stops counting toward a responder's public answer total. It cannot be claimed or answered — guarded in the WHERE clause of claimBy() and publishPrimaryAnswerFrom(), so the direct claim POST and a half-written answer are both refused. The asker keeps seeing their own question on /my-questions and its detail page, badged Hidden with the reason. No email is sent; Hide and Delete stay two distinct tools, with Delete remaining the silent one.

Verified with 20 new feature tests in tests/Feature/HideQuestionTest.php covering the admin hide/unhide actions, reason validation, the badge and 'Hidden only' filter, every public-visibility exclusion, the claim/answer guards, the answer count, the asker-facing notice, and Mail::assertNothingSent. Full suite green: 260 tests, 865 assertions. Pint clean. All Blade templates compile (php artisan view:cache).

Not done: the migration has not been run against the MySQL dev/production database — `php artisan migrate` is still needed there.
<!-- SECTION:FINAL_SUMMARY:END -->
