---
id: TASK-12
title: Declutter the main navigation for admin users
status: Done
assignee:
  - '@claude'
created_date: '2026-08-31 07:12'
updated_date: '2026-08-31 07:19'
labels: []
dependencies: []
type: enhancement
ordinal: 11000
---

## Description

<!-- SECTION:DESCRIPTION:BEGIN -->
When an admin is signed in, the desktop nav row carries four extra admin links (Questions admin, Users admin, Applications, Messages) on top of the shared links. The row runs out of horizontal space, labels wrap onto two lines and the bar looks cramped. Group the admin links behind a single 'Admin' dropdown on desktop, keep them as a flat labelled group in the mobile panel, and move the desktop/mobile breakpoint up so narrow laptops get the hamburger instead of a squeezed bar. The unhandled-messages badge must stay visible without opening the dropdown.
<!-- SECTION:DESCRIPTION:END -->

## Acceptance Criteria
<!-- AC:BEGIN -->
- [x] #1 Admin links are grouped under a single 'Admin' dropdown in the desktop nav row
- [x] #2 The unhandled contact-message count is visible on the collapsed Admin control without opening it
- [x] #3 The Admin dropdown uses its own Alpine scope and does not open or close the user dropdown
- [x] #4 The mobile panel lists the admin links as a flat labelled group, with the message badge intact
- [x] #5 Admin links remain visible only to users with the Admin role
- [x] #6 The nav row shows no wrapped labels at any viewport width when signed in as admin
<!-- AC:END -->

## Implementation Plan

<!-- SECTION:PLAN:BEGIN -->
1. Split the $navLinks builder in the @php block into $navLinks (shared) and $adminLinks (admin-only), keeping the Messages badge on its entry.
2. Compute $adminBadge as the sum of badges across $adminLinks so the collapsed Admin control can surface the unhandled-message count.
3. Desktop: render $adminLinks inside a new Admin dropdown with its own x-data scope (adminOpen) and its own @click.away, nested in the existing desktop wrapper so it cannot toggle the user dropdown. Use x-cloak to match repo idiom.
4. Mobile: render $adminLinks as a flat labelled group under a divider and an uppercase 'Admin' caption, mirroring the existing user-name group; keep per-link badges.
5. Move the desktop/mobile breakpoint from md to lg on the desktop row, hamburger button and mobile panel; widen the container to max-w-7xl and add whitespace-nowrap so labels stop wrapping.
6. Verify by rendering the nav as an admin (Blade lint + browser check at a range of widths).
<!-- SECTION:PLAN:END -->

## Implementation Notes

<!-- SECTION:NOTES:BEGIN -->
Split the @php link builder into $navLinks (shared) and $adminLinks (admin-only), with $adminBadge summing admin badges for the collapsed control. Desktop renders $adminLinks in an Admin dropdown scoped to its own x-data { adminOpen } with its own @click.away; mobile renders them flat under an uppercase 'Admin' caption, matching the existing user-name group. Breakpoint moved md -> lg on the desktop row, hamburger and mobile panel; container widened max-w-6xl -> max-w-7xl, gap-5 -> gap-4, added whitespace-nowrap.

Side effect worth noting: extracting the admin links moved 'My Questions' ahead of them in the desktop row for admins (previously admin links came first).

Verified: Blade compiles to valid PHP; nav renders for admin with the Admin button showing badge '1' from ContactMessage::unhandled(); member, creator and guest render zero admin links, zero /admin/ URLs and no adminOpen scope. x-collapse was not needed, but is available anyway (Livewire v4.3.3 bundles Alpine's Collapse plugin). Suite: 330/332 pass; the 2 failures (AlternativeAnswersUiTest) are pre-existing and reproduce with this change stashed.

Browser verification (headless Chromium against the rendered admin nav, built Tailwind CSS, Alpine booted from Livewire's asset):

Width sweep 1920/1440/1280/1024/1023/900/768/480/375 - desktop row down to 1024, hamburger from 1023 down; nav height constant at 66px and 0 wrapped labels at every width; no horizontal overflow. At the tightest desktop width the row needs 887px of 1024 available, so ~137px of slack remains.

Dropdown behaviour: initial both closed; click Admin -> Admin open, user closed; click user name -> Admin closed, user open; click Admin again -> Admin open, user closed; click outside -> both closed. aria-expanded tracks state.

Found and fixed during this check: clicking Admin while the user menu was open left BOTH menus open, because the user menu's 'open' flag lives on the desktop wrapper div so its @click.away does not fire for a click inside that wrapper. Fixed by having the Admin button also set open = false (the nested Alpine scope resolves 'open' to the wrapper's flag). The reverse direction was already handled by the Admin dropdown's own @click.away.

Admin dropdown items render as Questions / Users / Applications / Messages with the badge; mobile panel renders the same four under an uppercase ADMIN caption between the shared links and the user group.
<!-- SECTION:NOTES:END -->

## Final Summary

<!-- SECTION:FINAL_SUMMARY:BEGIN -->
Collapsed the four admin links in the main nav into a single 'Admin' dropdown on desktop and a flat labelled ADMIN group in the mobile panel, by splitting the shared $navLinks builder into $navLinks and $adminLinks in resources/views/layouts/navigation.blade.php. The unhandled-message count is summed into $adminBadge and shown on the collapsed Admin control so it is never hidden behind a click. The desktop/mobile breakpoint moved md -> lg and the container widened to max-w-7xl with whitespace-nowrap, so narrow laptops get the hamburger instead of a squeezed bar.

Verified in headless Chromium against the rendered admin nav: 0 wrapped labels and constant 66px nav height across 375-1920px, ~137px slack at the tightest desktop width, and correct mutually-exclusive open/close behaviour for the Admin and user dropdowns. Role isolation checked by rendering as member, creator and guest: zero admin links, zero /admin/ URLs, no adminOpen scope. Suite 330/332; the 2 AlternativeAnswersUiTest failures are pre-existing and reproduce with this change stashed.
<!-- SECTION:FINAL_SUMMARY:END -->
