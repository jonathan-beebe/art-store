---
id: IMPRV-036
type: improvement
status: open
created: 2026-09-04
---

# IMPRV-036: The listings overlay tests prove the right block shows per viewport

## Problem
`ListingController`'s overlay/takeover coverage (`app/Http/Controllers/Seller/ListingControllerTest.php:1296,1349`) asserts that markup carrying `2xl:hidden` and `<dialog open` is present somewhere in the response, a weaker claim than the workspace, the dialog, and the takeover each showing or hiding at the viewport widths their Tailwind `2xl:` classes name (audit `__local__/design/seller-portal/AUDIT.md` §6, FEAT-056 row: "viewport behavior covered by markup presence only").

## Goal
A regression that swaps which block carries which `2xl:` class, or breaks the breakpoint itself, fails a test.

## Outcome
The overlay/takeover test coverage proves which block is visible below `2xl` and which is visible at `2xl` and up, each pinned to its own element.

## Why it matters
A markup-presence assertion passes whether or not the classes are on the right element, so the test suite's green run today does not back the claim `docs/seller-portal.md`'s "Overlay vs takeover" section makes about what shows at which width.

## Discovery notes
- Pest has no CSS engine in this project, per FEAT-056's own Working notes on this gap — a rendered-viewport check needs either a headless-browser test (the codebase has none today) or a structural test that pins each block's own `2xl:` class onto its own element, e.g. asserting the workspace `<div>` specifically carries `2xl:block` and `inert`.
- `resources/views/seller/listings/detail-overlay.blade.php` is the view; `docs/seller-portal.md` § "Overlay vs takeover" is the doc section the test would back up.

## Related work
- FEAT-056 (found and left this behind)
