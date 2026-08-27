---
id: BUG-006
type: bug
status: open
created: 2026-08-27
---

# BUG-006: Error pages render unstyled

## Problem
A 500 in development renders Laravel's debug page as a wall of unstyled text: `SecurityHeaders` sends `Content-Security-Policy: default-src 'self'; …` with no `style-src`, so the debug page's inline styles are stripped. Outside debug the app has no custom error views at all beyond the three rate-limited pages — 404/419/500 fall to the framework's bare defaults, unbranded and off-theme on all three sites.

## Goal
Every error a person can hit renders readable: the styled framework debug page when `APP_DEBUG` is on, and minimal branded error views when it is off.

## Outcome
- [ ] With `app.debug` true, `SecurityHeaders` widens the CSP just enough for the framework debug page to style itself (`style-src 'self' 'unsafe-inline'`, plus what the page needs — verify against the actual rendered page); the production CSP is byte-for-byte unchanged, covered by a test asserting both modes.
- [ ] Minimal branded views for `404`, `419`, and `500` in resources/views/errors/, matching the tone and layout family of the existing rate-limited pages (per-site layout awareness the way those pages solved it, or one neutral shared page if per-site proves heavy — decide and record); each states what happened and one next step, no stack traces, no emoji.
- [ ] The 419 page tells the person the form expired and to go back and resubmit — the CSRF refusal path alignment §7 names.
- [ ] Feature tests render each error view; `make check` green; coverage 100%; journal updated.

## Why it matters
Error pages are where trust is most fragile; an unreadable wall of text at the exact moment something went wrong is the worst version of the platform a person ever sees.

## Discovery notes
- app/Http/Middleware/SecurityHeaders.php:25 (the CSP constant) and its sidecar test.
- resources/views/errors/rate-limited-{shop,seller,admin}.blade.php are the layout/tone reference and show how per-site error styling was solved once already.
- Laravel renders errors/{status}.blade.php automatically when present — no handler code needed for the non-debug views.
- Keep the debug-mode CSP change scoped: the conditional lives in the one place the header is set; no call site chooses a policy.

## Related work
- BUG-005 (the 500 that exposed this), IMPRV-004-era refusal pages
