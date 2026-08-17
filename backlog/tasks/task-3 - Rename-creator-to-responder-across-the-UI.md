---
id: TASK-3
title: Rename 'creator' to 'responder' across the UI
status: Done
assignee:
  - '@claude'
created_date: '2026-08-17 13:44'
updated_date: '2026-08-17 14:00'
labels: []
dependencies: []
ordinal: 3000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
The product no longer calls the people who answer questions 'creators'. All user-visible text should say 'responder'/'Responder' (and 'responders'/'Responders'), including page copy, page titles, flash messages, validation messages, email subjects and bodies, and the anonymous-answer byline.

Public URLs should change to match (/creators -> /responders, /creator/... -> /responder/...), with 301 redirects from the old paths so existing links and bookmarks keep working. Internal Laravel route names (creator.dashboard, creators.index, admin.creators) stay as they are, so no route() call sites need updating.

Code identifiers stay unchanged: class names (CreatorDashboard, CreatorApplication), file names, variable names, DB table names (creator_applications) and the UserRole enum's stored value 'creator'. Note that UserRole::label() and UserRoleInvite both derive their display string from the enum value via ucfirst(), so those need an explicit display mapping to avoid leaking 'Creator' into the UI.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 No user-visible string in blade views, page titles, flash messages, validation messages or email content contains 'creator'/'Creator' (singular or plural)
- [x] #2 The role invite email subject reads 'Responder' rather than 'Creator' while the stored role value remains 'creator'
- [x] #3 The anonymous answer byline reads 'a THRP responder'
- [x] #4 Public URLs are /responders, /responders/{user} and /responder/* ; the old /creators and /creator/* paths 301-redirect to the new ones
- [x] #5 Laravel route names, class names, file names, DB columns/tables and the UserRole enum value are unchanged
- [x] #6 Existing test suite passes, with test assertions on user-visible strings updated to the new wording
<!-- AC:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. Rename the public URL paths in routes/web.php (/creators -> /responders, prefix('creator') -> prefix('responder'), /admin/creators -> /admin/responders) while keeping every route NAME unchanged, and add Route::redirect 301s from the old paths.
2. Add an explicit display label to UserRole so the 'creator' enum value renders as 'Responder', and make App\Mail\UserRoleInvite use it instead of ucfirst($role->value).
3. Update Answer::ANONYMOUS_AUTHOR to 'a THRP responder'.
4. Sweep resources/views for user-visible text: page copy, headings, aria-labels, alt text, captions, x-slot titles and comments that read as UI wording. Leave blade variable names, component names, wire:key values and route() name arguments alone.
5. Sweep app/ for user-visible strings: Livewire ->title() calls, session flash MESSAGES (not the flash keys), addError() validation messages, and Mail Envelope subjects + email blade bodies.
6. Update tests that assert on user-visible strings (assertSee, assertSeeText) and any that assert on the changed URLs; leave class/variable names in tests as they are.
7. Run the full test suite plus php-cs-fixer, and verify no user-visible 'creator' remains by grepping the rendered strings.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Verification evidence:
- Full suite green: 232 tests / 784 assertions, 0 failures (php -d memory_limit=1G vendor/bin/phpunit). Note the suite needs a raised memory_limit; the default 128M OOMs in fakerphp, which is pre-existing.
- AC1 proven by rendering, not grep: tests/Feature/UiCopySaysResponderTest.php loads 14 real pages (home, /responders, a public profile, /apply, a question, settings, my-questions, the four responder-area pages, and the three admin pages) as guest/member/responder/admin, strips markup and asserts no case-insensitive 'creator' survives in the title, text nodes, or aria-label/alt/title/placeholder attributes. Same test asserts all three mail subjects and every email template's literal copy.
- AC2/AC3 covered by that same sweep plus the existing ANONYMOUS_AUTHOR assertions in CreatorProfileTest and AnswererLinkTest.
- AC4 proven by tests/Feature/LegacyCreatorUrlRedirectTest.php (5 tests): index, public profile (redirect AND the new URL still resolving), /responder + nested paths, /admin/responders, and that the status is specifically 301.
- AC5 verified via php artisan route:list: all 14 route NAMES unchanged (creators.index, creator.dashboard, admin.creators, ...) while paths now read /responders and /responder/*. git diff touches no class name, file name or migration.
- vendor/bin/pint --test passes on every changed file.

Decisions worth recording:
- Route NAMES kept, only URL paths changed, so no route() call site moved. Redirects are declared last in routes/web.php so they can never shadow a real route.
- Route::redirect only answers GET, so the POST claim endpoint has no legacy fallback. Only a page left open across the deploy would hit that, and it re-posts to the new path after a reload.
- UserRole::label() now maps explicitly instead of ucfirst(value); this is what keeps the stored 'creator' value from leaking into the invite email subject.
- Blade variable names left alone on purpose ($creator, $creators, $creatorName), as were the session flash KEY 'admin-creators-ok', the x-creator-avatar component and the creator_applications table.
- Renamed the seeder's demo users Carl/Clea Creator -> Responder; test fixtures that use those names create their own users and were unaffected.

Follow-up after review feedback: added a 308 redirect for legacy POSTs (TASK-3 originally shipped GET-only redirects).

Discovered while implementing it: Route::redirect() registers an any() route, and RouteCollection indexes routes by method+URI, so a later any() registration silently OVERWRITES an earlier method-specific route for the same path. Registering Route::post before the catch-all therefore did nothing — the POST still got 301. Fixed by dropping Route::redirect for /creator/{path} and declaring the GET (301) and POST (308) variants explicitly, which removes the ordering trap entirely.

Covered by two new tests in LegacyCreatorUrlRedirectTest: one asserts the 308 status and Location, the other re-sends the POST as a compliant client would and asserts the question is actually claimed. Suite now 234 tests / 791 assertions, green.

Note on pint: routes/web.php reports fully_qualified_strict_types, concat_space and ordered_imports, but so does the unmodified file at HEAD — pre-existing, left alone. The concat style in the new closures matches the file's existing __DIR__ . '/auth.php'.

Raised TASK-4 for the unrelated ApplicationReceived mail bug found during verification.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Renamed the 'creator' role to 'responder' everywhere a human reads it, while leaving the codebase's identifiers alone.

Changed: all blade copy, headings, aria-labels and table captions; Livewire page titles; admin flash messages; the duplicate-application validation message; the three mail subjects and email bodies; Answer::ANONYMOUS_AUTHOR ('a THRP responder'); and UserRole::label(), which now maps explicitly so the stored value 'creator' can no longer leak into the invite email as 'Creator'.

Public URLs moved to /responders, /responders/{user} and /responder/*, with permanent redirects from /creators, /creators/{user}, /creator, /creator/* and /admin/creators. Every Laravel route name, class name, file name, DB table/column and the UserRole enum value are untouched, so no route() call site changed.

Verified with the full suite (232 tests, 784 assertions, green) including two new tests: LegacyCreatorUrlRedirectTest covers the 301s and that the new URLs still resolve, and UiCopySaysResponderTest renders 14 real pages across guest/member/responder/admin and asserts no 'creator' survives in visible text, titles, screen-reader attributes, mail subjects or email templates. pint --test clean on all changed files.
<!-- SECTION:FINAL_SUMMARY:END -->
