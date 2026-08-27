---
id: BUG-006
type: bug
status: resolved
created: 2026-08-27
---

# BUG-006: Error pages render unstyled

## Problem
A 500 in development renders Laravel's debug page as a wall of unstyled text: `SecurityHeaders` sends `Content-Security-Policy: default-src 'self'; …` with no `style-src`, so the debug page's inline styles are stripped. Outside debug the app has no custom error views at all beyond the three rate-limited pages — 404/419/500 fall to the framework's bare defaults, unbranded and off-theme on all three sites.

## Goal
Every error a person can hit renders readable: the styled framework debug page when `APP_DEBUG` is on, and minimal branded error views when it is off.

## Outcome
- [x] With `app.debug` true, `SecurityHeaders` widens the CSP just enough for the framework debug page to style itself (`style-src 'self' 'unsafe-inline'`, plus what the page needs — verify against the actual rendered page); the production CSP is byte-for-byte unchanged, covered by a test asserting both modes.
- [x] Minimal branded views for `404`, `419`, and `500` in resources/views/errors/, matching the tone and layout family of the existing rate-limited pages (per-site layout awareness the way those pages solved it, or one neutral shared page if per-site proves heavy — decide and record); each states what happened and one next step, no stack traces, no emoji.
- [x] The 419 page tells the person the form expired and to go back and resubmit — the CSRF refusal path alignment §7 names.
- [x] Feature tests render each error view; `make check` green; coverage 100%; journal updated.

## Why it matters
Error pages are where trust is most fragile; an unreadable wall of text at the exact moment something went wrong is the worst version of the platform a person ever sees.

## Discovery notes
- app/Http/Middleware/SecurityHeaders.php:25 (the CSP constant) and its sidecar test.
- resources/views/errors/rate-limited-{shop,seller,admin}.blade.php are the layout/tone reference and show how per-site error styling was solved once already.
- Laravel renders errors/{status}.blade.php automatically when present — no handler code needed for the non-debug views.
- Keep the debug-mode CSP change scoped: the conditional lives in the one place the header is set; no call site chooses a policy.

## Related work
- BUG-005 (the 500 that exposed this), IMPRV-004-era refusal pages

## Working

**CSP** — `SecurityHeaders` appends `; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'` to the existing constant only when `app()->hasDebugModeEnabled()`; the production string is untouched. Both directives are needed, not just `style-src`: the framework's debug page (Laravel's Tailwind/Alpine renderer, `Illuminate\Foundation\Exceptions\Renderer\Renderer`) is a self-contained bundle that builds its own content from an inline `<script>`, so blocking scripts left the page blank rather than merely unstyled — confirmed by reproducing the original 500 (curl, cookie jar, magic-link sign-in) and inspecting the response body for inline `<style>`/`<script>` tags and any external `url()`/font/image references (none — no widening beyond style/script was needed).

**Error views** — one neutral shared layout (`x-layouts.error`) rather than three per-site variants: Laravel resolves `errors/{status}.blade.php` by status code alone, with no route or guard context handed in, so there's no reliable signal for which site's chrome (and which auth guard) to render — the per-site rate-limited pages get that context because a controller already knows the site when it calls `tooManyRequests()`. Decision recorded here per the ticket's discovery note. `404`/`419`/`500` each state what happened and offer one next step (a homepage link for 404/500; the 419 page's next step is instructional text only, since a working "go back" link would need JS or an `onclick`/`javascript:` href that CSP and the "JS off" rule both rule out).

**Wiring confirmed, not just written**: a generic uncaught exception maps to `HttpException(500, ...)` in `Handler::prepareResponse()` only when `app.debug` is off (or the exception is already an `HttpExceptionInterface`), which is what lets `errors::500` render instead of the debug bundle; `TokenMismatchException` maps to `HttpException(419, ...)` regardless of debug. Verified with `app(ExceptionHandler::class)->render(...)` directly rather than through a live request, because CSRF verification is disabled during tests (`PreventRequestForgery::runningUnitTests()`) and there was no way to provoke a genuine 419 otherwise; a real GET to a nonexistent route exercises 404 end to end (also checked live at localhost:8000).

A subtlety hit while writing the debug-mode test: the framework's debug page embeds a syntax-highlighted excerpt of every stack frame, including the test method's own file, so asserting the *absence* of the branded page's exact copy from that same test file is self-referential (the copy sits a few lines away, in the "outside debug" test, and would leak into the excerpt on file proximity rather than a real regression). Distinguished the two pages instead by the raw exception message showing up (a debug-only leak, intentional here) and by response size.

### Numbers
2329 tests, 6714 assertions, 100% lines.
