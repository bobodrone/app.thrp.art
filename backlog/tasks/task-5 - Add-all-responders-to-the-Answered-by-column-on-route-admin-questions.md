---
id: TASK-5
title: 'Add all responders to the ''Answered by'' column on route: /admin/questions'
status: Done
assignee:
  - '@claude'
created_date: '2026-08-25 15:17'
updated_date: '2026-08-25 15:35'
labels: []
dependencies: []
ordinal: 5000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Comma separated
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 The 'Answered by' column on /admin/questions lists every responder with a visible answer on that question, comma separated
- [x] #2 The main answer's responder is listed first, followed by alternative responders oldest-first by published_at
- [x] #3 Soft-deleted (hidden) answers are excluded from the list
- [x] #4 Anonymity and profile-linking rules are unchanged per name: admins see real nicknames, anonymous answers are never linked
- [x] #5 A question with no visible answer still renders an em dash
- [x] #6 The listing query issues no per-row N+1 queries for answer authors
<!-- AC:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. Add Question::creditedAnswers(): every visible answer, main one first then alternatives oldest-first by published_at, skipping answers whose author was deleted.
2. Eager-load answers.author (id,name,role) in AdminQuestionsTable::render alongside the existing primaryAnswer.author load.
3. Loop over creditedAnswers() in the 'Answered by' cell of questions-table.blade.php, rendering x-answerer-name per responder with ', ' between them; keep the em dash when the list is empty.
4. Feature tests: order, hidden answers excluded, hidden main answer, anonymity/linking, em dash, and an N+1 guard.
5. Run the suite and Pint.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Crediting logic lives on the model as Question::creditedAnswers() rather than in the Blade view: it reuses otherAnswers() for the publication-order sort and keeps the 'visible answers only' rule in one place. Answers with a deleted author (created_by is nullOnDelete) are filtered out so the comma list never has an empty slot — this preserves the old cell's behaviour of showing an em dash for an authorless answer.

A hidden main answer no longer blanks the cell: any surviving alternatives are still credited, which matches the 'all responders' rule and pairs with the existing 'Answer removed' badge in the Status column.

Validation: 'php artisan test' — 240 passed, 802 assertions. Pint clean on the three touched PHP files. The N+1 guard (query count for 1 vs 5 rows) was checked to be meaningful by temporarily dropping the answers.author eager load: 26 vs 10 queries, test fails as expected.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
The 'Answered by' column on /admin/questions now credits every responder with a visible answer, comma separated, main answer first and alternatives behind it in publication order. Added Question::creditedAnswers(), eager-loaded answers.author in AdminQuestionsTable::render, and rewrote the cell to loop x-answerer-name over that list. Anonymity and profile-linking rules are unchanged per name, hidden answers are excluded, and an empty list still renders an em dash. Verified by six new feature tests in AdminQuestionsTableTest (order, exclusion of hidden answers and of a hidden main answer, admin view of an anonymous alternative, em dash, N+1 guard); full suite 240 passed, Pint clean.
<!-- SECTION:FINAL_SUMMARY:END -->
