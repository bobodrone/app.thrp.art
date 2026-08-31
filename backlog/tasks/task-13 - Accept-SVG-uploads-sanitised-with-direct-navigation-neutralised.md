---
id: TASK-13
title: 'Accept SVG uploads, sanitised, with direct-navigation neutralised'
status: Done
assignee:
  - '@claude'
created_date: '2026-08-31 15:08'
updated_date: '2026-08-31 15:28'
labels: []
dependencies: []
references:
  - resources/views/components/image-upload.blade.php
  - app/Livewire/Concerns/HandlesImageUploads.php
  - config/uploads.php
  - .htaccess-prod
  - tests/Feature/AnswerImageUploadTest.php
priority: medium
type: feature
ordinal: 12000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
Answer images and profile pictures currently reject SVG. People can still pick an SVG (the picker's accept list is only a hint, and the drag-and-drop path in the upload widget ignores it entirely), so the file uploads and is only then refused.

Add SVG as a supported format, safely. Two things make this tractable here:

- Every user image is rendered through an `<img src>` tag ([answer-image.blade.php](resources/views/components/answer-image.blade.php), [creator-avatar.blade.php](resources/views/components/creator-avatar.blade.php)). Browsers load SVG referenced from `<img>` in secure static mode: no script execution, no external fetches. The in-page display path is therefore already safe by browser design.
- The actual risk is the direct URL. Uploads land on the `public` disk, served straight off the `public/storage` symlink by Apache without Laravel running. `/storage/answers/x.svg` is a top-level document on the app origin, so script in it would run with access to the session cookie. An attacker uploads, then sends the link to a moderator or admin.

So the work is: sanitise what gets stored, and make direct navigation to a stored SVG harmless. Two independent layers, because a sanitiser bypass alone should not be enough.

Deliberately out of scope: rendering SVG to a raster on upload, and moving uploads off the public disk behind a Laravel route. Both were considered and are the stronger fix, but they need a runtime that can execute a rasteriser (unconfirmed on the IONOS shared host) or a second origin. Also out of scope: adding an app-wide Content-Security-Policy, which the app lacks entirely and is worth tracking separately.

Production is IONOS shared hosting, Apache with .htaccess, rsync deploy — there is no nginx config or CDN to hang headers on.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 An SVG chosen as an answer image or profile picture is accepted, stored, and renders on the answer and profile pages
- [x] #2 Stored SVG files contain no script elements, no on* event handlers, no javascript: URLs and no DOCTYPE declaration, verified against a corpus of known-malicious SVGs kept in the test suite
- [x] #3 An SVG referencing an external resource (http/https href, external <image>, cross-document <use>, @font-face src) is rejected with a clear validation error shown as soon as the file is picked
- [x] #4 A file with an .svg extension that is malformed XML, or has no <svg> root element, is rejected with a clear validation error; SVG is not validated via the mimetypes rule, whose finfo result for SVG is unreliable
- [x] #5 SVG has its own configurable size cap in config/uploads.php, defaulting far below the raster cap, with its own error message
- [x] #6 Navigating directly to a stored .svg URL cannot execute script on the app origin: the response carries Content-Disposition attachment and X-Content-Type-Options nosniff
- [x] #7 Raster uploads are unaffected: JPEG/PNG/GIF/WebP still open inline when their URL is visited directly, and existing upload tests pass unchanged
- [x] #8 The upload widget offers SVG in its accept list and names it in the on-screen format hint
- [x] #9 Serving a stored SVG sets Content-Disposition attachment, X-Content-Type-Options nosniff and a restrictive Content-Security-Policy, asserted by a feature test rather than verified by hand on the production host
- [x] #10 A file whose type is not in the accepted list is rejected in the browser, without uploading, on both paths into the widget: choosing it in the picker and dropping it on the drop zone. The same inline error text is shown either way and the input is cleared
<!-- AC:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
Decision taken before planning: SVG is served through a Laravel route rather than
via Apache headers. `deploy.sh:53` excludes `public/.htaccess` from the rsync, so an
.htaccess control would need a manual server edit, would no-op silently if
mod_headers is off on IONOS, and could not be covered by the test suite. Raster
images are untouched by all of this and keep static-serving off the public symlink.

1. Dependency: `composer require enshrined/svg-sanitize:^0.22`. Note it is
   GPL-2.0-or-later — the only non-permissive dependency. Fine here because a
   hosted app is not distribution, but worth knowing.

2. `config/uploads.php`: `mime_types` currently does double duty as the picker's
   accept list and as the `mimetypes:` validation list. SVG must be in the first
   and never the second (finfo reports SVG inconsistently). Split them: keep
   `extensions`/`mime_types` as the raster-only validation lists, add
   `svg_enabled` (bool, env) and `svg_max_kb` (default 128), and derive the accept
   list from both.

3. New rule `App\Rules\SafeSvg`, applied only when the picked file's extension is
   `svg`; raster files keep the existing `mimetypes:` + `max:` rules. It rejects,
   with a distinct message each: over `svg_max_kb`; any DOCTYPE in the raw bytes
   (checked before parsing — XXE and entity expansion); not well-formed XML;
   root element is not `svg`; any external reference — `href`/`xlink:href` starting
   http/https/protocol-relative, `<image>` with a non-`data:` href, `<use>` whose
   href is not a same-document `#fragment`, or `@font-face src:` inside `<style>`.
   Parse with LIBXML_NONET and without LIBXML_NOENT.

4. `HandlesImageUploads::storeImage()`: for SVG, run the sanitiser with
   `removeRemoteReferences(true)`, write the sanitised output (never the uploaded
   bytes) under a random filename on the **private `local` disk**; raster continues
   to `store()` on the public disk unchanged. `deleteImage()` branches on the same
   extension. No migration: the existing `image_path`/`avatar_path` columns already
   carry the extension, so it is enough to decide the disk from the path.

5. `HandlesImageUploads::previewUrl()`: for a staged SVG return a `data:` URI built
   from the *sanitised* content instead of `temporaryUrl()`. Livewire's temp-file
   preview URL would otherwise serve the raw, unsanitised upload from the app
   origin.

6. New route `GET /media/{path}` (name `media.svg`, `where('path', '.*')`) with a
   single-action controller: reject anything not ending `.svg`, reject traversal by
   resolving against the disk root and checking the realpath prefix, 404 when
   missing. Responds with `Content-Type: image/svg+xml`,
   `Content-Disposition: attachment`, `X-Content-Type-Options: nosniff`,
   `Content-Security-Policy: default-src 'none'; sandbox`, and a long immutable
   `Cache-Control` (filenames are random). `Content-Disposition: attachment` does
   not affect `<img>` subresource loads, only top-level navigation — that is what
   makes the page still render while the direct URL is inert.

7. `Answer::imageUrl()` and `User::avatarUrl()`: return `route('media.svg', ...)`
   for `.svg` paths, the existing public-disk URL otherwise.

8. `image-upload.blade.php`: add SVG to the accept list and to the on-screen format
   hint when enabled.

9. Tests. New `tests/Fixtures/svg/` corpus (script element, onload handler,
   `javascript:` href, DOCTYPE entity bomb, external `<image>`, remote `<use>`,
   `@font-face src`) plus a clean control file. New `SvgUploadTest` covering
   accept/store/render, each rejection path, and assertions on the stored bytes.
   New route test for the headers, traversal, non-svg path and 404. Existing
   `AnswerImageUploadTest` and `CreatorProfileTest` must pass unchanged.

10. `vendor/bin/pint` and the full `php artisan test` before finalisation.

Noted but out of scope, to raise with the user rather than fold in silently: the
drag-and-drop handler in the upload widget still bypasses the accept list entirely
for genuinely unsupported types (PDF and so on), so those upload before being
refused. Supporting SVG removes the reported symptom but not that hole.
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Scope addition agreed with the user before implementation: the client-side pre-check on the drag-and-drop path (previously flagged as out of scope) is included in this task rather than deferred to a follow-up. Added as AC #10.

Two design changes were made during implementation, both away from what the plan said:

1. Dropped the planned SvgOrRaster dispatching rule. Wrapping the raster rules in
   a nested Validator inside a rule object swallowed their rule names, so
   `assertHasErrors(['answerImage' => 'max'])` in the existing AnswerImageUploadTest
   started failing. imageRules() now inspects the pending upload and returns either
   the raster rules or SafeSvg, so `mimetypes` and `max` stay first-class and the
   existing messages and tests are untouched. Simpler, and one class fewer.

2. sanitiseSvg() catches Throwable as well as checking for false. The sanitiser
   throws ("Got 0 svg elements, expected exactly one") rather than returning false
   for input that is well-formed XML but not an SVG. previewUrl() runs during the
   re-render after validation fails, so an uncaught throw there turned a clean
   rejection into a 500.

Also fixed while in the area: User::deleteImage() (the anonymisation path) resolved
the disk from `uploads.avatar.disk` unconditionally, which would have orphaned every
SVG avatar on the private disk. Disk resolution now lives in one place,
uploaded_image_disk() in app/helpers.php, used by both that path and the trait.

Verification:
- php artisan test => 355/357. The only two failures are
  AlternativeAnswersUiTest::test_the_card_counts_every_answer_on_the_question and
  ::test_a_single_answer_card_keeps_the_original_hint, both confirmed failing on a
  clean stash of main before any of this work. Unrelated and pre-existing.
- New: SvgUploadTest (19 tests) and ServeUploadedSvgTest (6 tests), both green.
- composer lint (pint) passes across the repo.
- AC #10 is browser behaviour and cannot be proved by the PHP suite. Verified in
  headless Chrome against the actually-rendered widget markup: 14/14 checks pass,
  covering both entry paths for an unsupported file and for each accepted one, and
  confirming the refused file never reaches the wire:model listener. The project has
  no browser-test infrastructure, so that harness was run from a scratch directory
  and is not committed.

Note on the capture listener: it sits on the wrapper div, not on the input. A
capture listener on an ancestor is guaranteed by the DOM spec to run before the
target's own listeners; on the input itself it would merely race Livewire's,
since capture and bubble listeners on the target fire in registration order. The
browser harness confirms the ordering empirically.

New dependency: enshrined/svg-sanitize ^0.22, which is GPL-2.0-or-later — the only
non-permissive dependency in the project. Fine for a hosted app, since GPL
obligations attach to distribution and serving over HTTP is not that.

Unrelated pre-existing finding: `composer audit` reports 11 advisories against
guzzlehttp/guzzle and one other transitive package. Not introduced here and not
addressed here.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
SVG uploads are now accepted for answer images and profile pictures, behind two independent layers.

Write side: a new App\Rules\SafeSvg rejects a DOCTYPE on the raw bytes before any parser sees them (XXE and entity expansion), non-XML, a non-<svg> root, anything over a separate 128 KB cap, and any reference outside the file itself. What survives is passed through enshrined/svg-sanitize with removeRemoteReferences on, and only the sanitised bytes are written, under a random filename. SVG deliberately skips the mimetypes rule, whose finfo result for SVG is unreliable in both directions.

Read side: SVG is stored on the private local disk rather than the public one, and leaves only through GET /media/{path} (App\Http\Controllers\ServeUploadedSvg), which sets Content-Disposition attachment, nosniff and a sandboxing CSP, and refuses non-SVG paths, files outside the configured upload directories, and traversal. Content-Disposition is the load-bearing part: honoured for navigations, ignored for subresource loads, so pages keep rendering these images while the bare URL is inert. This replaced the originally planned Apache Header directives, which deploy.sh excludes from the rsync, which no-op silently without mod_headers, and which no test could prove were in force.

Raster uploads are untouched: same public disk, same static serving, same rules and messages.

The widget now offers SVG and, separately, enforces the accepted list in the browser on both entry paths, so an unsupported file is refused inline instead of being uploaded in full and rejected on submit — the original report. The drag-and-drop path previously ignored the accept list entirely.

Verified with php artisan test (355/357; the two failures are pre-existing on main, confirmed against a clean stash), 25 new tests across SvgUploadTest and ServeUploadedSvgTest driven by a committed corpus of malicious SVG fixtures, composer lint clean, and a headless-Chrome harness over the real rendered widget for the client-side behaviour (14/14).
<!-- SECTION:FINAL_SUMMARY:END -->
