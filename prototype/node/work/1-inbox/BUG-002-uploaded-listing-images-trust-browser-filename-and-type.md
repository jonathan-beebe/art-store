---
id: BUG-002
type: bug
status: open
created: 2026-08-23
---

# BUG-002: Uploaded listing images trust the browser's filename and content type

## Problem
The only check on a listing image upload is `/^image\//` against the
**client-supplied** `Content-Type` (`core/listings/listing-draft.ts:8`).
`extensionForImage` (`sites/seller/listing-image-upload.ts:5-21`) maps five
known content types to a safe extension and otherwise falls back to
`path.extname(filename)` — also client-supplied. `@fastify/static` serves
`PUBLIC_ROOT` (which contains `uploads/`) at prefix `/` (`app.ts:60`).

Verified: a multipart part with `Content-Type: image/anything` and
`filename="evil.html"` was accepted and stored as
`imagePath: '/uploads/1cd1304b-e70c-4a05-a08c-35f30952746f.html'`, served back
as `text/html` from the app's own origin. Separately, `image/svg+xml` is in
the allow-list (`listing-image-upload.ts:10`) and is served as
`image/svg+xml`, which executes script when navigated to directly. Identity
cookies are `httpOnly`, so this is not straight cookie theft, but it is
same-origin script execution by any signed-in seller.

`@fastify/multipart` is registered with no `limits`
(`sites/seller/index.ts:17`: `portal.register(multipart, { attachFieldsToBody: true })`).
The plugin defaults `fileSize` to `fastify.initialConfig.bodyLimit` (1 MB,
since `buildApp` sets no `bodyLimit`) and leaves `files` at `Infinity` and
`parts` at 1000. Verified: a 2 MB PNG returns
`413 application/json {"code":"FST_REQ_FILE_TOO_LARGE"}` instead of the
listing form re-rendering with a field error. The 1 MB cap is implicit —
stated nowhere in the form copy or in `listingDraftErrors`.

The uploads directory is a module-level path constant, not injected state:
`sites/seller/routes/listings.ts:38` —
`const UPLOADS_DIR = path.join(import.meta.dirname, '..','..','..','..','public','uploads')`.
Uploads land inside the bind mount / image filesystem with no configuration
point for a real deployment (volume or object store), and the integration
tests write real files into `src/public/uploads` on every run, leaving litter
that only `.gitignore` hides.

## Goal
An uploaded listing image is verified by its bytes, size-limited with an
explicit stated cap, served without executing as script, and written to a
directory that comes from config.

## Outcome
- An upload whose bytes are not a PNG/JPEG/GIF/WebP is refused with a field error.
- SVG is not accepted.
- A file over the stated size limit re-renders the form with a field error rather than a JSON 413.
- Uploads are served with `X-Content-Type-Options: nosniff`.
- The uploads directory comes from config.
- Tests write uploads to a temp dir.

## Why it matters
"Parse, don't validate at every boundary … parsed once in the shell into
narrow types" — a browser-supplied filename or `Content-Type` header is
unparsed input, not a narrow type. Explicit limits and error handling belong
at the framework boundary. "Shared state via decorators (no module-level
singletons)" and "Mock only what crosses the process boundary" apply to the
uploads path — the filesystem is a process boundary and the current constant
leaves it unisolated in tests.

## Discovery notes
Reject anything whose extension is not in the known map rather than falling
back to the filename, sniff the magic bytes of the buffer instead of trusting
the header, drop `image/svg+xml` or serve uploads with
`Content-Disposition: attachment` and `X-Content-Type-Options: nosniff` from a
dedicated prefix that is not the same static root as the stylesheet.

Pass explicit `limits: { fileSize, files: 1, parts }` sized for a photograph,
state the same number in the form's help text, and catch
`FST_REQ_FILE_TOO_LARGE` in the site's error handler so it re-renders the form
with an image error instead of a JSON 413.

Add `uploadsDir` to `AppConfig`, decorate it (or reach it via
`request.server.config`), and point the test config at a per-test temp
directory from `node:fs/promises mkdtemp`.

Files expected to touch: `app/sites/seller/listing-image-upload.ts`,
`app/sites/seller/routes/listings.ts`, `app/core/listings/listing-draft.ts`,
`app/sites/seller/index.ts`, `app/config.ts`, `app/app.ts`,
`app/test/build-test-app.ts` (or wherever `TEST_CONFIG` lives).

No dependency on another ticket in this batch; independent of BUG-003 through BUG-006.

## Related work
- 05-shell-ops.md — "An uploaded file's extension is taken from the browser's filename and served from the app origin"
- 05-shell-ops.md — "`@fastify/multipart` is registered with no `limits`"
- 05-shell-ops.md — "The uploads directory is a module-level path constant, not injected state"
