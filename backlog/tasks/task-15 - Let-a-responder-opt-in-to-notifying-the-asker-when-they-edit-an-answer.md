---
id: TASK-15
title: Let a responder opt in to notifying the asker when they edit an answer
status: Done
assignee:
  - '@BREG'
created_date: '2026-09-01 18:19'
updated_date: '2026-09-01 18:30'
labels: []
dependencies: []
references:
  - app/Livewire/CreatorQuestionDetail.php
  - resources/views/components/answer-editor.blade.php
  - app/Jobs/NotifyAskerOfAnswer.php
  - app/Mail/AnswerNotification.php
  - resources/views/emails/answer-notification.blade.php
priority: medium
type: feature
ordinal: 14000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Answer edits are silent today, and should stay that way by default: `CreatorQuestionDetail::updateAnswer()` writes the new body and image in place, leaves `published_at` untouched, and dispatches no mail. That is right for a typo fix minutes after publishing, but wrong for a substantive rewrite the asker has already read and moved on from — and there is currently no way to tell them at all short of contacting them off-platform.

Rather than a permanent public "edited" marker, give the responder the choice at save time: a checkbox on the answer edit form, unchecked by default, along the lines of "Notify the person who asked this question about this edit". Only when it is ticked does the asker get an email.

Points the implementer needs to know:

- The edit form is the shared `x-answer-editor` component, used for both the main answer and alternatives, driven by `CreatorQuestionDetail`. The checkbox belongs to that one form.
- The flag must not survive an edit session. `startEditAnswer()`, `cancelEditAnswer()` and the reset at the end of `updateAnswer()` all clear the draft state; the new flag has to be cleared with them, so it can never carry over into the next answer a responder opens.
- The existing mail is wrong for this case. `AnswerNotification` is subjected "Your question has been answered — THRP" and its body reads as a first answer; an edit needs its own subject and wording. `NotifyAskerOfAnswer` is the job to extend or model an edit variant on.
- `NotifyAskerOfAnswer::handle()` already returns early when the question has no asker, which covers deleted and anonymised accounts. Keep that behaviour.
- Admins may edit answers written by someone else, since `Answer::isEditableBy()` allows them. Decide deliberately whether the checkbox is offered to an admin in that situation, and make the email wording correct for whoever it is offered to.
- Mail must only go out after the save succeeds, never when the edit is rejected by the ownership re-check or by validation.

Open question for whoever picks this up: nothing would stop a responder ticking the box on every save in a row. Edits are rare and responders are vetted, so a guard may not be worth it — but confirm that is an accepted risk, or add a simple limit, rather than leaving it unconsidered.

Related: TASK-14 makes the edit form reachable in the first place.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 The answer edit form shows a notify-the-asker checkbox that is unchecked every time the form is opened, for both the main answer and an alternative
- [x] #2 Saving an edit with the checkbox unticked sends no mail, and leaves the current silent-edit behaviour, including the unchanged published_at, exactly as it is
- [x] #3 Saving an edit with the checkbox ticked emails the asker, with a subject and body written for an edited answer rather than a first answer, linking to the question
- [x] #4 The notify flag is cleared when an edit is cancelled, when a different answer is opened for editing, and after a successful save, so it never carries into a later edit
- [x] #5 No mail is sent when the save is rejected by validation or by the ownership re-check in updateAnswer()
- [x] #6 No mail is attempted when the question has no asker, for example an anonymised or deleted account
- [x] #7 Feature tests cover the unticked, ticked, rejected-save, and no-asker cases
<!-- AC:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. Extend `NotifyAskerOfAnswer` with an `edited` flag rather than adding a parallel job: recipient, null-asker guard, URL building and preview are identical, and only the wording differs. Same for `AnswerNotification` — an `edited` bool that switches the subject and the heading/lead/button copy in `emails/answer-notification.blade.php`.
2. Copy stays neutral about who edited ("An answer to your question has been updated"), so the same mail is correct whether the author or an admin made the change. That settles the admin question left open at creation: the checkbox is offered to anyone who may edit.
3. Add `public bool $notifyAskerOfEdit = false` to `CreatorQuestionDetail`, cleared in `startEditAnswer()`, `cancelEditAnswer()` and the post-save reset in `updateAnswer()` — all three already reset draft state, so the flag joins that list.
4. In `updateAnswer()`, dispatch only after the save succeeds, past both the ownership re-check and validation. Read the flag before the reset.
5. Add the checkbox to `x-answer-editor`, which both the main answer and alternatives already share, so one change covers both. Hide it when the question has no asker — offering to notify a deleted account is a lie, even though the job would drop the mail anyway.
6. Feature tests: unticked sends nothing and leaves published_at; ticked mails the asker with the edit wording; rejected save (validation, and an answer this user may not edit) sends nothing; no-asker question sends nothing; the flag does not survive cancel, a switch to another answer, or a successful save.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Extended `NotifyAskerOfAnswer` and `AnswerNotification` with an `edited` flag rather than adding a parallel job and mailable: recipient, null-asker guard, preview and URL building are identical, and only the subject and three lines of copy differ.

The email copy deliberately says nothing about *who* edited ("An answer to your question has been updated ... has been changed since you were last told about it"). That settles the admin question left open at creation without branching: the checkbox is offered to anyone who may edit, and the same wording is honest whether the author or an admin made the change.

Dispatch sits after the `answerBeingEdited()` ownership re-check, after validation, and after `$answer->update()`, reading the flag before the reset clears it — so a rejected edit mails nothing. Both rejection paths are covered by tests.

Finding worth recording: AC #6 rested on a premise the schema does not allow. `questions.asked_by` is NOT NULL with `cascadeOnDelete`, and `User::anonymise()` keeps the row (name becomes "Deleted user"), so a stored question always has an asker. I had first written a `canNotifyAsker` conditional to hide the checkbox — that was dead code and has been removed rather than left in. The job's own null-asker guard is untouched and is now exercised as the backstop it is, via `setRelation('asker', null)`.

Related pre-existing behaviour, not changed here: notifying an anonymised asker sends to `deleted-{id}@{ANONYMISED_EMAIL_DOMAIN}`, a dead address. That is already true of the first-answer notification, so it is untouched by this task.

Validation: `php artisan test --filter NotifyAskerOfEditTest` — 13 passed. Full suite: 383 tests, 381 passed. The 2 failures (AlternativeAnswersUiTest::test_the_card_counts_every_answer_on_the_question and ::test_a_single_answer_card_keeps_the_original_hint) are pre-existing on clean main, confirmed under TASK-14. `./vendor/bin/pint --dirty` clean.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Answer edits stay silent by default; the responder can now opt out of that silence one edit at a time.

`CreatorQuestionDetail` gained `$notifyAskerOfEdit`, bound to a checkbox on the shared `x-answer-editor` so the main answer and alternatives both get it. It is off whenever the form opens and is cleared by `startEditAnswer()`, `cancelEditAnswer()` and the post-save reset, so a tick can never carry into the next answer edited. When ticked, `updateAnswer()` dispatches `NotifyAskerOfAnswer` with a new `edited` flag once the save has actually landed; `AnswerNotification` and its view switch subject and copy to edit wording, saying nothing about who made the change so the mail is correct for a responder and an admin alike. `published_at` still stays put and an unticked save behaves exactly as before.

Verified by 13 new tests in tests/Feature/NotifyAskerOfEditTest.php: unticked sends nothing and leaves published_at; ticked queues the job with edited=true and delivers mail with the edit subject, the edit body, none of the first-answer wording and a link to the question; admin and alternative-answer paths; the flag cleared on cancel, on switching answers and after saving; nothing sent when validation or the ownership re-check rejects the save; and the job's null-asker guard. A first answer still sends the original unchanged notification. Full suite 381/383, the 2 failures pre-existing on main.
<!-- SECTION:FINAL_SUMMARY:END -->
