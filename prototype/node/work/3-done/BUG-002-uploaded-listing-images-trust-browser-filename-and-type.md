---
id: BUG-002
type: bug
status: resolved
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

## Working
Re-validated against the code as it stands: the problem as described still
applied — `imageContentType`/`extensionForImage` trusted the multipart part's
own `Content-Type` and filename, `multipart` was registered with no `limits`,
and `UPLOADS_DIR` was a module-level constant under `sites/seller/routes/listings.ts`.

What changed:
- `app/core/listings/image-format.ts` (new, +test): pure `sniffImageFormat(bytes: Uint8Array)`
  reading magic bytes for PNG/JPEG/GIF/WebP, returning `ImageFormat | null`.
  Exports `IMAGE_FORMAT_EXTENSIONS` and `IMAGE_FORMAT_CONTENT_TYPES`. SVG (and
  anything else) sniffs to `null` — no allow-list entry to remove, since SVG
  was never a magic-byte format to begin with.
- `app/core/listings/listing-draft.ts` (+test): `ListingDraftFields.imageContentType`
  replaced by `imageFormat?: UploadedImageFormat | null` (`ImageFormat |
  'unrecognized'`). `imageError` refuses only `'unrecognized'`; a known format
  or no upload passes.
- `app/sites/seller/listing-image-upload.ts` (+test): `extensionForImage`
  (which fell back to the client's filename) is gone. `saveUploadedListingImage`
  now takes a sniffed `ImageFormat` and looks the extension up from
  `IMAGE_FORMAT_EXTENSIONS` — nothing client-supplied reaches the filename.
  Added `MAX_IMAGE_UPLOAD_BYTES` (5 MB) and `MAX_IMAGE_UPLOAD_MB`.
- `app/sites/seller/listing-form.ts` (+test): `listingDraftFieldsFrom` now takes
  the caller's sniffed `imageFormat` as a parameter instead of reading the
  part's `mimetype` header itself — sniffing needs the buffer, which is an
  async read the route already owns.
- `app/sites/seller/routes/listings.ts` (+test): `create`/`update` read the
  uploaded part's bytes once (`readUploadedImage`), sniff the format, build
  `ListingDraftFields` from the sniffed result, validate, and only then save
  using the already-read buffer — no double consumption of the multipart
  stream. `UPLOADS_DIR` constant replaced by `request.server.config.uploadsDir`.
  Added `renderOversizedImageForm` (exported for `index.ts`'s error handler):
  re-renders the edit form for the upload's owning listing when the URL and
  identity cookie resolve to one (looked up independent of the `preHandler`
  identity hooks, which have not run yet when this fires — see below),
  otherwise the blank new-listing form, both at 422 with an image field error.
- `app/sites/seller/index.ts`: `@fastify/multipart` now registers
  `limits: { fileSize: MAX_IMAGE_UPLOAD_BYTES, files: 1 }`. A
  `portal.setErrorHandler` scoped to the seller plugin catches
  `FST_REQ_FILE_TOO_LARGE` and calls `renderOversizedImageForm` instead of
  letting the plugin's default JSON 413 answer.
- `app/config.ts` (+test): added `uploadsDir`, env `UPLOADS_DIR`, defaulting
  to `<public root>/uploads` via the same `import.meta.dirname`-relative
  convention `app.ts`'s `PUBLIC_ROOT` uses.
- `app/app.ts` (minimal, per territory): a second `@fastify/static`
  registration at `root: config.uploadsDir, prefix: '/uploads/'` (specific
  prefix wins over the root `/` registration for anything under `/uploads`),
  `decorateReply: false`, `setHeaders` adding `X-Content-Type-Options:
  nosniff`. The root registration is untouched.
- `app/test/build-test-app.ts`: `buildTestApp` now builds a fresh `mkdtemp`
  uploads directory per test app (unless the caller supplies its own
  `config`), removed in `close()`. `TEST_CONFIG.uploadsDir` carries an inert
  placeholder — no test currently reads `TEST_CONFIG` outside this file.

Deliberately left alone:
- The `parts` limit from the Discovery notes' suggested `limits: { fileSize,
  files: 1, parts }` — the Required Behavior section only asks for `{
  fileSize, files: 1 }`, and the form has a fixed, small number of parts, so
  a `parts` cap adds nothing a photograph-sized `fileSize` doesn't already
  bound.
- `app/plugins/identity.ts` — out of territory. `renderOversizedImageForm`
  reaches the seller id through the already-exported `identityCookieValue`
  rather than the `preHandler`-resolved `request.currentSeller`, because
  `@fastify/multipart`'s eager buffering (and its `FST_REQ_FILE_TOO_LARGE`
  throw) runs in a `preValidation` hook, which completes before any
  `preHandler` (identity resolution included) runs — confirmed by reading
  `node_modules/@fastify/multipart/index.js` and `app/plugins/identity.ts`.
- `placeholderImageSvg`/`placeholderImageDataUri` (`core/listings/placeholder-image.ts`) —
  a separate, always-inline SVG generated server-side from the listing title
  for listings with no upload. Not part of the uploaded-file path this ticket
  covers.

Verified: `npm run check` (typecheck, lint, full suite) and `npm run coverage`
both green — 1205/1205 tests passing, coverage 99.43% lines / 95.19% branches
/ 98.88% functions (thresholds 90/80). The suite total reflects concurrent
work from other tickets landing in this shared tree (RFCTR-001, FEAT-012);
this ticket's own tests are the new `image-format.test.ts` (10 cases) plus
the additions/rewrites in `listing-draft.test.ts`, `listing-form.test.ts`,
`listing-image-upload.test.ts`, `routes/listings.test.ts` (4 new cases: a
spoofed filename/`Content-Type`, an SVG, an oversized upload on both the new
and edit forms, plus a real-PNG-header happy path that also checks the
served `nosniff` header), and `config.test.ts`.
