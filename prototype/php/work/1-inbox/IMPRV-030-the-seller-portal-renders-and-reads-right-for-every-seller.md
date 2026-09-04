---
id: IMPRV-030
type: improvement
status: open
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
