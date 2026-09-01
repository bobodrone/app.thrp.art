---
id: TASK-16
title: Say "response" instead of "answer" across the UI
status: Done
assignee:
  - '@BREG'
created_date: '2026-09-01 18:50'
updated_date: '2026-09-01 18:58'
labels: []
dependencies: []
references:
  - resources/views/
  - app/Livewire/CreatorQuestionDetail.php
  - app/Enums/QuestionStatus.php
  - app/Mail/AnswerNotification.php
  - app/Mail/NewQuestionNotification.php
  - app/Http/Requests/AnswerQuestionRequest.php
  - tests/Feature/UiCopySaysResponderTest.php
priority: medium
type: enhancement
ordinal: 15000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The product calls a responder's work on a question a "response", not an "answer". The status badge was already changed to read "Responded" (commit 1341290), but that was a single-file patch: the rest of the interface still says "answer" throughout — page titles, headings, buttons, flash and validation messages, email subjects and bodies, table columns and bylines.

This is the same shape of change as TASK-3 (creator -> responder) and should follow it: user-visible text only, code untouched.

Change: page copy, headings, page titles, button labels, aria-labels/alt text, flash messages, validation and error messages, email subjects and email bodies, admin table column headers, and nav labels.

Leave alone: class names (`Answer`, `AnswerNotification`, `CreatorQuestionDetail`), method and property names (`submitAnswer`, `$answerDraft`, `isAnswerableBy`), Livewire `wire:model`/`wire:key` values, blade component names (`x-answer-body`), route names (`creator.answered`), DB tables and columns (`answers`, `primary_answer_id`, `last_answered_at`), and the `QuestionStatus::Answered` enum's stored value `answered`. `QuestionStatus::label()` still returns "Answered" and should be brought in line, noting that `x-status-badge` duplicates that mapping rather than calling it.

Decisions taken with the user before starting:

1. Bylines use the noun: "Answered by Jane" becomes "Response by Jane", including the admin table column header. Relative dates read "Responded 2 days ago". The responder's list becomes "Questions I've Responded To".
2. Verb forms become "respond": "Claim & answer" -> "Claim & respond", "Write your answer" -> "Write your response", "Submit Answer" -> "Submit Response".
3. The About page prose is deliberately excluded. It plays the two words against each other on purpose — "The response may not be the answer you were looking for (it may not be an answer at all)" — and replacing "answer" there destroys the point. It is editorial copy, not interface chrome.
4. The contact form and its notification email are reworded to "reply" rather than "response": "if your message needs a reply". That removes "answer" while keeping "response" meaning a responder's work on a question.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 No user-visible string in blade views, page titles, button labels, flash messages, validation messages or email content reads "answer"/"Answer" — with the About page prose as the one deliberate, documented exception
- [x] #2 Bylines and the admin table column read "Response by <name>"; relative dates read "Responded <time> ago"
- [x] #3 Verb forms read "respond": the claim button, the write-a-response form heading and its submit button
- [x] #4 The contact form and its notification email say "reply", not "answer" or "response"
- [x] #5 QuestionStatus::label() returns "Responded", matching the badge, while the stored enum value stays "answered"
- [x] #6 Class names, method and property names, blade component names, wire:model values, route names and DB tables/columns are unchanged
- [x] #7 A rendering-based test loads the real pages as guest, member, responder and admin and asserts no user-visible "answer" survives in text or in aria-label/alt/title/placeholder attributes, mirroring UiCopySaysResponderTest
- [x] #8 The full existing suite passes, with assertions on user-visible strings updated to the new wording
<!-- AC:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. Enumerate the real targets first: user-visible strings only. Blade text nodes, x-slot titles, aria-label/alt/title/placeholder attributes, `->title()` calls, `addError()` and validation message strings, `session()->flash()` message values (not keys), and Mail `Envelope` subjects plus email blade bodies.
2. Sweep `resources/views/` by hand rather than with a blanket sed — the word appears far more often as a code identifier (`$answerDraft`, `wire:model="answer"`, `x-answer-body`, `$renderedAnswer`, `primaryAnswer`) than as copy, and blade mixes both on the same line.
3. Sweep `app/`: `CreatorQuestionDetail` messages, `AdminQuestionsTable` and `AdminUserManagement` flash text, `AnswerQuestionRequest` validation messages, `AnswerNotification` and `NewQuestionNotification` subjects, and the answer-notification and new-question email bodies.
4. Bring `QuestionStatus::label()` to "Responded". Leave the stored value `answered`. Note in passing that `x-status-badge` duplicates the mapping instead of calling `label()` — do not refactor that here, it is outside this task.
5. Skip `resources/views/about.blade.php` entirely, and make the guard test skip it explicitly with a comment saying why, so nobody "fixes" it later.
6. Contact form and its email: reword to "reply".
7. Update the assertions in the existing suite that name the old copy.
8. Add `tests/Feature/UiCopySaysResponseTest.php` modelled on `UiCopySaysResponderTest`: render the real pages as guest/member/responder/admin, strip markup, assert no case-insensitive "answer" survives in text or in the attributes listed above, plus the mail subjects and email bodies. This is what proves AC1 by rendering rather than by grep.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Swept by hand rather than with sed: in blade the word appears far more often as an identifier (`$answerDraft`, `wire:model="answer"`, `x-answer-body`, `primaryAnswer`, `answers_count`) than as copy, and both often sit on the same line. Every replacement was asserted to match an expected number of occurrences before being applied.

Wording applied, per the decisions taken with the user before starting:
- Nouns -> "response"; verbs -> "respond" ("Claim & respond", "Write your response", "Submit Response", "Add your response").
- Bylines and the admin column -> "Response by <name>"; relative dates -> "Responded 2 days ago"; the responder's list -> "Questions I've Responded To"; nav -> "My Responses" / "Responses".
- Emails: "Your question has a response" (first response) and "A response to your question has been updated" (the TASK-15 edit variant).
- Contact form and its email -> "reply": "if your message needs a reply", "Replying to this email reaches Grace directly."
- `QuestionStatus::label()` now returns "Responded", matching `x-status-badge`. The badge still duplicates that mapping instead of calling `label()` — the two had already drifted apart, which is why the enum still said "Answered" after commit 1341290 changed the badge. Left as-is deliberately: refactoring it is not this task, but the new test pins both.

/about is excluded on purpose and the guard test says so in a comment, so nobody "fixes" it later. Its prose plays the two words against each other — "The response may not be the answer you were looking for (it may not be an answer at all)" — and rewording it costs the passage its point.

Finding worth recording: a page-level sweep is not sufficient on its own. `x-answer-editor`'s default heading was "Edit answer", used by the main response's edit form, and no HTTP GET ever renders it — the form only appears after `startEditAnswer`. Same blind spot covers the admin table's edit modal. So the guard has a second test that drives those through Livewire. I verified it fails when the old heading is put back, rather than trusting that it would.

Validation: full suite 387 passed, 0 failed (up from 386; the 4 new guard tests less the ones folded in). `php artisan test --filter UiCopySaysResponseTest` — 4 passed. `./vendor/bin/pint --dirty` clean. `git diff HEAD` touches no class name, method name, property, wire:model value, route name, migration or column.

Note: `php artisan view:clear` is needed after switching branches or stashing, or blade assertions run against stale compiled views.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
All user-visible copy now says "response" for a responder's work on a question; "answer" survives only as code identifiers and in one deliberate exception.

Swept page copy, headings, page titles, button labels, alt text, the sr-only table caption, flash and validation messages, admin table columns, nav labels, and email subjects and bodies across 24 blade views and 7 PHP classes. Verbs became "respond", bylines became "Response by <name>", and the contact form and its email became "reply" — that feature is about email correspondence, not responses to questions. `QuestionStatus::label()` was still returning "Answered" while the badge said "Responded"; both now agree, with the stored enum value unchanged.

Deliberately excluded: the About page prose, which plays "response" and "answer" against each other on purpose. The exclusion is documented in the guard test so it is not later mistaken for an oversight.

Untouched: class names, method and property names, blade component names, wire:model and wire:key values, route names, DB tables and columns, and the 'answered' enum value.

Verified by tests/Feature/UiCopySaysResponseTest.php, modelled on UiCopySaysResponderTest: it renders 16 real pages as guest, member, responder and admin, strips markup, and asserts no case-insensitive "answer" survives in the title, text nodes or aria-label/alt/title/placeholder attributes, and does the same for all four mailables including both wordings of the response notification. A second test drives the edit forms and the admin edit modal through Livewire, because those only render after an interaction — that is where the one real miss was, `x-answer-editor`'s default heading, which no GET could ever have shown. I confirmed that test fails when the old heading is restored. Full suite 387 passed, 0 failed; Pint clean.
<!-- SECTION:FINAL_SUMMARY:END -->
