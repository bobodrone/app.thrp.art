---
id: TASK-10
title: Require Responder applicants to accept the THRP conditions
status: Done
assignee:
  - '@claude'
created_date: '2026-08-28 06:37'
updated_date: '2026-08-28 06:42'
labels: []
dependencies: []
type: feature
ordinal: 9000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Responders must explicitly agree to THRP's community conditions before applying. The apply form (/apply) gets a required 'I hereby accept & confirm the conditions' checkbox, with the full conditions text in a foldable (collapsed by default) block placed under the 'Why do you want to be a responder?' field and above the submit button. Acceptance is recorded on the application row so admins have a record that the applicant agreed. Requested by the client: 'when Responders sign up is it possible to have them tick a box with some terms and conditions? Just something to say they will behave or have their access revoked.'
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 The apply form shows a foldable block containing the full conditions text, collapsed by default, between the message field and the submit button
- [x] #2 A required checkbox labelled 'I hereby accept & confirm the conditions' sits with that block
- [x] #3 Submitting without ticking the checkbox fails validation with a clear message and creates no application
- [x] #4 Submitting with the checkbox ticked stores the acceptance timestamp on the creator_applications row
- [x] #5 Feature tests cover both the rejected (unticked) and accepted (ticked) paths
<!-- AC:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. Migration: add nullable `terms_accepted_at` timestamp to creator_applications.
2. Model: make it fillable + cast to datetime.
3. New blade component x-responder-terms holding the conditions copy in a collapsed <details>, styled like x-markdown-cheatsheet.
4. CreatorApplicationForm: add public bool $acceptedTerms = false with 'accepted' validation rule + custom message; persist terms_accepted_at = now() on create.
5. Blade: render the component + required checkbox between the message field and submit; show per-field error.
6. Tests in ApplyTest: unticked submit fails and creates nothing; ticked submit stores terms_accepted_at. Update existing passing submissions to tick the box.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Implemented as planned. Conditions copy lives in a new x-responder-terms blade component (collapsed <details>, styled after x-markdown-cheatsheet) so it can be reused if the same text is ever needed elsewhere. Validation uses Laravel's 'accepted' rule on a new $acceptedTerms property with a custom message; acceptance is stamped as terms_accepted_at on the creator_applications row (new nullable column) so admins have a record. Verified: rendered /apply HTML shows the collapsed <details> and checkbox between the message field and the submit button; php artisan test => 325 passed (12 in ApplyTest, including the new unticked-rejection, timestamp-recorded and page-content tests); pint clean.

Follow-up (same request, 2026-08-28): the acceptance date is now surfaced in the admin applications inbox, under each pending application — '✓ Accepted the conditions on 27 Aug 2026, 09:15' (absolute timestamp rather than diffForHumans, since this is a consent record). Applications predating the checkbox show 'Applied before the conditions checkbox existed — no acceptance on record.' Covered by two new tests in AdminApplicationsTest; full suite 327 passing, pint clean.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Added a required 'I hereby accept & confirm the conditions' checkbox to the responder application form, with the full THRP conditions text in a collapsed foldable block between the message field and the submit button. Unticked submissions fail validation with 'Please accept and confirm the conditions before applying.' and create nothing; accepted ones stamp terms_accepted_at on the application. Verified by rendering /apply and by the full suite: 325 tests passing, pint clean.
<!-- SECTION:FINAL_SUMMARY:END -->
