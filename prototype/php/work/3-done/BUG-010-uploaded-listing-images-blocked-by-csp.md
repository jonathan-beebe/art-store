---
id: BUG-010
type: bug
status: resolved
created: 2026-08-27
---

# BUG-010: Uploaded listing images render broken under the CSP

## Problem
An uploaded image reaches storage (the file at
http://localhost:8000/storage/listings/&lt;hash&gt;.jpg opens directly) but
renders as a broken image in the app, with the browser reporting:
"Loading the image 'http://localhost:8000/storage/listings/….jpg'
violates the following Content Security Policy directive: "img-src
'self' data:". The action has been blocked."

The CSP is `default-src 'self'; img-src 'self' data:; …`
(app/Http/Middleware/SecurityHeaders.php:32). `ListingImage::url()`
(app/Models/ListingImage.php:55) returns
`Storage::disk('public')->url($path)`, an ABSOLUTE URL built from
APP_URL (config/filesystems.php:46, APP_URL=http://localhost:8000). An
absolute URL is only same-origin when the browser's address matches
APP_URL exactly — browsing via 127.0.0.1:8000, a LAN address, or any
host other than `localhost:8000` makes every image cross-origin and
`img-src 'self'` blocks it.

## Goal
An uploaded image always renders wherever the app itself renders.

## Outcome
Images uploaded through the Images screen display on the seller and shop
pages in a real browser, regardless of which host/port the app is
browsed at, with the CSP unchanged in spirit (no wildcard img-src).

## Why it matters
The plural-images feature shipped by DSGN-002 is visibly broken at first
use — the seller uploads a photo and the editor, hub, and listing page
all show a broken-image glyph.

## Discovery notes
Reported live right after uploading through the new Images screen.
Root-cause candidates, advisory: emit relative `/storage/...` paths from
`ListingImage::url()` (relative URLs are always same-origin, the same
reason `Listing::imageUrl()`'s placeholder never had this problem), or
confirm and document a strict APP_URL-must-match-browser rule. Verify
the `storage:link` symlink exists in the container/runtime image while
in there — a missing link yields 404s that can masquerade as broken
images too.

## Related work
- prototype/php/work/3-done/DSGN-002-retire-legacy-form-unify-editor-into-rows.md
- BUG-006 (the CSP's debug-mode shape — prototype/php/work/3-done/)

## Working

Root cause confirmed: `ListingImage::url()` called
`Storage::disk('public')->url($path)`, which builds an absolute URL from
the `public` disk's `url` config value — `APP_URL`
(`config/filesystems.php:46`). Any browser address other than that exact
host/port makes the image cross-origin, and the CSP's `img-src 'self'`
blocks it.

Fix: `ListingImage::url()` now returns `'/storage/'.$this->path` — a
relative path, always same-origin regardless of the browsing host. The
CSP constant is untouched.

Every consumer of `ListingImage::url()` (`Listing::imageUrl()`'s cover
lookup, `ListingConfiguratorSummaries::images()`'s `urls` array, and the
seller Images-row and shop-listing Blade views) only ever places the
value in a server-rendered `<img src>` attribute or hands it back to
Blade for the same. None parses it as an absolute URL or sends it to a
different origin, so the relative form works everywhere it's used.

Symlink check: the link exists in both runtime paths already. Dev's
`docker/entrypoint.sh` runs `php artisan storage:link --force` on every
container start. The production `runtime` stage in `Dockerfile` creates
the equivalent link directly (`ln -sfn
/var/www/src/storage/app/public public/storage`) at image build time —
outside the `storage/` volume mount, so it survives the volume starting
empty on first boot and persists across restarts. No missing-symlink
contribution to this bug; no change needed there.

Files changed:
- `src/app/Models/ListingImage.php` — `url()` returns a relative path.
- `src/app/Models/ListingImageTest.php` — pinned test updated to assert
  the relative form.

Tests:
- `it serves its file from the public disk as a relative path` (new,
  replaces the old absolute-URL assertion) — confirmed failing against
  the pre-fix code (`'/storage/listings/heron.png'` expected vs.
  `'http://localhost:8000/storage/listings/heron.png'` actual), then
  passing after the fix.
- Full suite: 2717 passed (7753 assertions), `make test`.

Refactor suggestions (not done): none — the fix is a one-line change to
an existing single-purpose method.
