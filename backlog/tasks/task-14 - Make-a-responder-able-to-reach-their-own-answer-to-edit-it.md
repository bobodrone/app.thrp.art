---
id: TASK-14
title: Make a responder able to reach their own answer to edit it
status: Done
assignee:
  - '@BREG'
created_date: '2026-09-01 18:19'
updated_date: '2026-09-01 18:25'
labels: []
dependencies: []
references:
  - app/Models/Answer.php
  - app/Http/Controllers/CreatorAnsweredController.php
  - resources/views/creator/answered.blade.php
  - resources/views/questions/show.blade.php
  - resources/views/components/question-action.blade.php
  - resources/views/layouts/navigation.blade.php
  - app/Livewire/CreatorQuestionDetail.php
priority: medium
type: bug
ordinal: 13000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Editing an answer is permitted indefinitely at the model layer — `Answer::isEditableBy()` allows the author or an admin, with no time or status window — but the edit UI only exists on the responder view at `/responder/questions/{id}`. Once a responder navigates away from that page after answering, they have no obvious route back, so editing feels like it expires.

The one existing path is Responder Dashboard -> "My Answered Questions ->" (`/responder/answered`), which links each row to the responder view. Three gaps make it a dead end in practice:

1. `/responder/answered` is only linked from the dashboard body. It is absent from the main navigation, so a responder who lands anywhere else cannot find it.
2. Alternative answers cannot be edited from that list. `CreatorAnsweredController` selects questions where the responder wrote *any* answer (`answeredBy` scope), but `resources/views/creator/answered.blade.php` decides the link with `Question::isAnswerEditableBy()`, which only inspects the *primary* answer. A responder whose answer is an alternative gets a "View ->" link to the read-only public page instead of "Edit answer ->". This is a bug, not an intended restriction.
3. The public question page `/questions/{id}` offers a responder who has already answered nothing at all — `x-question-action` renders empty for them, and `QuestionController::show()` computes no edit affordance.

Outcome: a responder can get from a published answer of theirs back to its edit form without knowing a URL, whether that answer is the main one or an alternative.

Note the responder view is gated by the `role:creator,admin` middleware, so any link added to the public page must only render for a viewer who passes that gate.

Related: the opt-in edit notification is tracked separately.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 A responder whose only answer on a question is an alternative sees "Edit answer" (not "View") for that question on /responder/answered, and following it opens the responder view where the edit form is available
- [x] #2 The answered-questions list is reachable from the main navigation for responders and admins, not only from the dashboard body
- [x] #3 On the public question page, a viewer who may edit an answer shown there gets a visible link to that answer edit form
- [x] #4 The public question page renders no edit link for viewers who may not edit, including anonymous visitors, the asker, and responders who did not write an answer on that question
- [x] #5 Editing remains permitted with no time limit, and no "edited" marker is shown publicly
- [x] #6 Feature tests cover the alternative-answer case on /responder/answered and the presence and absence of the edit link on the public page
<!-- AC:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. Add `Answer::isEditFormOpenTo(?User)` — ownership via the existing `isEditableBy()` AND `role->isAtLeast(Creator)`, since the edit form only exists on the responder view behind the `role:creator,admin` gate. A demoted author owns their answer but has no way in.
2. Add `Question::hasEditableAnswerFor(?User)` — true when any visible answer passes `isEditFormOpenTo()`. Replaces the primary-only `isAnswerEditableBy()` check in the answered list.
3. Eager-load `answers` in `CreatorAnsweredController` and switch `resources/views/creator/answered.blade.php` to `hasEditableAnswerFor()`, fixing the alternative-answer dead end (AC1).
4. Add an answered-list entry to `$navLinks` in `resources/views/layouts/navigation.blade.php`, which the desktop row and mobile panel share (AC2).
5. In `resources/views/questions/show.blade.php`, render an "Edit answer" link to `creator.questions.show` beside the main answer and beside each alternative the viewer may edit, calling `isEditFormOpenTo()` per answer in the blade — matching the file's existing style of asking the model directly (AC3, AC4). No controller change.
6. Feature tests: alternative-only answerer sees "Edit answer" on /responder/answered; public page shows the link to the author and to an admin, and not to a guest, the asker, an unrelated responder, or a demoted member author.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Added `Answer::isEditFormOpenTo()` rather than widening `isEditableBy()`: ownership and reachability are genuinely different questions here. `isEditableBy()` stays the authorisation rule the Livewire component re-checks on save; the new method adds the `role->isAtLeast(Creator)` gate that decides whether it is honest to *show* a link, since the form only exists on the responder view behind `role:creator,admin`. An author demoted to member would otherwise be linked straight to a 403 — covered by a test.

`Question::hasEditableAnswerFor()` mirrors the loaded/unloaded branching of the neighbouring `hasAnswerFrom()`; `CreatorAnsweredController` eager-loads `answers` so the list takes the in-memory branch and adds no queries per row.

New `x-edit-answer-link` component holds the render decision once, used by both the main answer and each alternative on the public page. Its copy varies on authorship ("Edit your answer" vs "Edit answer") so an admin is not told that someone else's answer is theirs.

Nav label is "My Answers" for a responder and "Answered" for an admin, matching the page heading, which already distinguishes the two.

Validation: `php artisan test --filter EditAnswerReachabilityTest` — 13 passed. Full suite: 370 tests, 368 passed. The 2 failures (AlternativeAnswersUiTest::test_the_card_counts_every_answer_on_the_question and ::test_a_single_answer_card_keeps_the_original_hint) were confirmed pre-existing by stashing this branch and re-running on clean main. `./vendor/bin/pint --dirty` clean.

Note for anyone hitting odd results locally: `php artisan view:clear` is needed after a git stash cycle, or nav assertions fail against stale compiled blades.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Editing an answer was never time-limited — `Answer::isEditableBy()` allows the author or an admin indefinitely — but the edit form only exists on the responder view, and nothing led a responder back to it once they left the page they answered on.

Fixed the three gaps. The answered list now asks `Question::hasEditableAnswerFor()` (any visible answer of theirs) instead of the primary-only `isAnswerEditableBy()`, so an alternative no longer dead-ends in a read-only "View" link. The list is in the main nav for responders and admins, not just the dashboard body. The public question page carries an "Edit your answer" link beside each answer the viewer may edit, via a new `x-edit-answer-link` component gated on `Answer::isEditFormOpenTo()` — ownership plus the responder-role gate, so a demoted author is not sent to a 403.

No change to what editing is permitted, and no public "edited" marker.

Verified by 13 new tests in tests/Feature/EditAnswerReachabilityTest.php covering both list cases, nav presence for responder/admin and absence for a member, and the public link for author, alternative author and admin against its absence for a guest, the asker, an unrelated responder and a demoted author. Full suite 368/370; the 2 failures are pre-existing on clean main.
<!-- SECTION:FINAL_SUMMARY:END -->
