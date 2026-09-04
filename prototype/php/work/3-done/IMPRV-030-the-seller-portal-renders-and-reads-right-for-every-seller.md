---
id: IMPRV-030
type: improvement
status: resolved
created: 2026-09-04
---

# IMPRV-030: The seller portal renders and reads right for every seller

## Problem
The audit (`__local__/design/seller-portal/AUDIT.md` §2) found user-visible defects in the merged seller portal: every inline `<script>` is blocked by the production CSP (the mobile nav, the New listing dialog, the admin drawer), the thread pane collapses between 1280px and 1550px, the listings overlay is a modal with a live page behind it, bracketed config placeholders render to sellers, the statement prints white on white in dark mode, the bar strips and several controls are silent or ambiguous to assistive technology, and six sentences in the help articles claim behavior the app lacks.

## Goal
A seller on a phone, on a laptop, on a print-out, or with a screen reader can use every seller screen, and nothing on a page says something the app does not do.

## Outcome
- No view under `resources/views` renders a `<script>` without `src`; a smoke test proves it with `APP_DEBUG=false`. The mobile nav drawer, the New listing dialog, and the admin drawer work in production.
- The thread's context rail appears only at `2xl`; below that it stacks; the thread header wraps its actions under the title on a narrow pane. No width between `lg` and `2xl` shows a one-word-per-line title.
- The listings overlay at `2xl` is a real modal: the header sits inside the inert region, focus moves to the dialog on open, Escape closes it, one `<h1>` per page. Below `2xl` the takeover renders as today.
- Bracketed or empty support config values render as text, never as links or contact details.
- The statement and the earnings tables print legibly in dark mode; the statement offers a working print control without JavaScript (a `<form>` or plain link fallback), and the label page has one.
- Bar strips carry `role="img"` and an accessible name; the store preview's headings nest in order; repeated controls carry distinct accessible names (which picture, which period); no `<img src="">`; the seller's store form shows the alt text it collected; `aria-current` is `page` for navigation and `true` for filters, everywhere.
- The four help articles say only what the app does (audit §2 item 12 lists the six sentences).
- `make precommit` green; `make check` green before the PR.

## Why it matters
Two of these defects mean a seller in production cannot create a listing or navigate on a phone; the rest are the difference between a portal that renders and one that works for everyone.

## Discovery notes
- Items 1, 2, 5, 7, 9, 11, 12 of `__local__/design/seller-portal/AUDIT.md` §2, with the owner's notes on items 2 and 5: the rail as a request-only sliding panel is a design question recorded in `__local__/design/seller-portal/DECISIONS.md`, so this ticket does the breakpoint fix; keep the modal at `2xl` if the accessible version stays small (an external script of ~20 lines plus moving the header inside `inert`), otherwise render the takeover at every width.
- External scripts follow `public/configurator-autosubmit.js` and `public/sort-autosubmit.js`: a `data-` hook, `defer`, no inline handlers.
- `FeedTest` (audit §4, first bullet) asserts Tailwind strings; move it to `data-` markers while touching the feed.

## Related work
- FEAT-051..061 (the portal), MAINT-008
- IMPRV-021 (commit gate), IMPRV-026..029 (shared chrome idioms)

## Working

- Item 1 (CSP): the nav drawer script is identical between the seller and
  admin layouts bar the element id, so it moves to one shared
  `public/nav-drawer.js` keyed off a `data-nav-drawer` attribute instead of
  two id-bound copies. The New listing dialog gets its own
  `public/new-listing-modal.js`, same shape as the existing autosubmit
  scripts.
- Item 5 (listings overlay): the owner's answer in `DECISIONS.md` ("Stick
  with the modal") settles §2.5 — the dialog stays. `_header.blade.php`
  gets an `asHeading` prop (the detail route renders "Listings" as a `<p>`,
  since the listing's own title is the page's one heading) and a
  `withNewListingDialog` prop, so the header can render twice — once inside
  the `inert` workspace behind the modal, once in the takeover, which
  carries the one real New listing dialog — without a duplicate id.
  `public/listing-detail-dialog.js` upgrades the dialog to `showModal()` at
  `2xl` (matchMedia-synced, so a resize crossing the breakpoint opens or
  closes it), with `autofocus` on Close; without the script the dialog
  stays exactly as CSS-visible-but-non-modal as it already was. (The
  review pass below turns `_header.blade.php` into a real component and
  makes closing the dialog navigate away.)
- Two items from the owner's walk (§6), both cosmetic: the store
  Pictures row's Remove button moves from an absolute overlay on the
  thumbnail's corner to a stacked cell under it, so it never overlaps
  the picture beside it at the row's narrower widths. The earnings
  Sales tile read "new" when neither this period nor the last one sold
  anything; `PeriodFigures::salesChange()` special-cased both-zero
  locally with a new `RangeChange::empty()`. (The review pass folds the
  rule into `RangeChange::between()` itself instead — see below.)
- All eight audit-item fixes plus the two owner-walk fixes landed as
  ten small commits; the branch's full `git log --oneline` lists them
  alongside the review pass. `make check` (lint, assets, coverage-gated
  suite) is green.

## Review pass

A review of the first pass came back with twelve findings; all twelve are
fixed, each its own small commit on top of the original ten (after
rebasing onto `php/seller-portal-next`, which had moved to IMPRV-041/
FEAT-064 in the meantime — the journal keeps every base entry and appends
only this ticket's two lines).

- **Escape painted the modal over an inert page.** `dialog:not([open])`'s
  default `display:none` loses to the markup's own `2xl:flex` once `.close()`
  removes `open`, so nothing hid the dialog after a genuine close while
  `inert` kept the workspace behind it unreachable either way.
  `listing-detail-dialog.js` now listens for the dialog's own `close`
  event and navigates to `data-close-href` ($backHref) — guarded so the
  matchMedia down-transition close (viewport drops below `2xl`) does not
  navigate, since that swap stays on the page.
- **`aria-current` was inconsistent both ways.** Lane tabs and the shared
  inbox tabs (filters) carried `page`; the five admin cells, both
  messaging inboxes, and the seller fulfillment cells (pane rows) carried
  `true`. Both flipped to the stated rule; one sweep test renders a page
  from each family and checks the pairing. Four pre-existing tests pinned
  the old, inconsistent values and needed their own fix.
- **Only one of five bar strips was named.** The other four already sit
  beside their own count or label; the component now defaults to
  `aria-hidden="true"` when no `labelledby` is given, instead of a bare,
  unlabeled `role`-less graphic.
- **The statement's `dark:` classes never matched anything.** `dark:` is
  a custom Tailwind variant scoped to `.supports-dark`; every layout's
  `<body>` carries it except the statement's own standalone one, so the
  earlier print fix's `print:dark:*` overrides were dead code. Fixed with
  one class.
- **`RangeChange::empty()` fixed one of twelve call sites.** The rule
  moves into `between()` itself (`previous === 0` → `current === 0 ?
  empty : "new"`); the factory is gone, and the two admin analytics call
  sites that read `RangeChange::between()` directly for a true zero/zero
  case get the same fix their own tests now pin.
- **The CSP smoke test only rendered three pages.** A `Finder` sweep over
  every `resources/views/**/*.blade.php` file's own source, plus a count
  of `'unsafe-inline'` in the production CSP (one, `style-src`'s own),
  replaces it.
- **Backdrop-click dismissal isn't native.** `nav-drawer.js` and both
  layouts' comments said Escape and a backdrop click were both native
  `<dialog>` behavior; only Escape is — the nav drawers' backdrop click
  works through a flex-1 filler button `nav-drawer.js` already wires via
  `data-drawer-close`, and `listing-detail-dialog.js` gets the same
  `event.target === dialog` handler `new-listing-modal.js` already had,
  since its dialog has no such filler button.
- **Several tests asserted literal Tailwind class strings.**
  `data-thread`, `data-thread-header`, `data-thread-rail`,
  `data-thread-actions`, and `data-listings-title` scope the thread and
  listings-header assertions to their own elements; `data-store-picture`
  replaces a negative match on the picture row's old absolute-position
  classes; the statement's print-color check moves to its own `<h1>`.
- **The inert-region test compared string positions.** Replaced with
  `Symfony\Component\DomCrawler\Crawler` (added as a dev dependency,
  with `symfony/css-selector`): `[inert] [data-new-listing-open]`
  matches, and `#new-listing-dialog` has no `[inert]` ancestor.
- **Three refactors moved logic out of Blade.** The support page's
  placeholder guard is `SupportDesk::published(?string): ?string`, unit
  tested and now covering `phone_hours` too. `customers/show.blade.php`'s
  inline placeholder fallback is `Fulfillment::itemImageUrl()`, seller-
  scoped like `itemLabel()` beside it. `_header.blade.php` is now
  `resources/views/components/seller/listings-header.blade.php`, an
  anonymous component with `@props(['asHeading' => true,
  'withNewListingDialog' => true])` in place of `@php($x ??= true)`.
- **`docs/seller-portal.md` still described the pre-fix behavior.** The
  rail section said `xl`; the Overlay vs takeover section described
  `<dialog open>` with no JS. Both now match what shipped.
- **Contrast clauses** ("not X", "rather than") in `RangeChange.php`,
  `PeriodFigures.php`, `bar-strip.blade.php`, `print-button.js`, six test
  names, and three commit subjects (reworded via a non-interactive
  `git filter-branch --msg-filter`, since `git rebase -i` is unavailable
  here) are now positive statements.

`make check` is green; `git log --oneline php/seller-portal-next..php/au-ui`
on the branch lists all twenty-two commits — the original twelve (ten
fixes plus the ticket's start and resolution) and ten for the review
pass.
