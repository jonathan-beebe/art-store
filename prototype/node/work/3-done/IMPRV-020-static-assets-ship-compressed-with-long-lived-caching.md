---
id: IMPRV-020
type: improvement
status: resolved
created: 2026-08-25
---

# IMPRV-020: Static assets ship compressed with long-lived caching

## Problem
No response in the app carries `Content-Encoding` — there is no compression path at all. `public/app.css` is 31,252 bytes and render-blocking (`views/partials/head.ejs:7`); it would be roughly 6KB gzipped. The static registration (`app.ts:99-102`, set by BUG-008) serves it with `maxAge: '5m'` under a name that never changes across deploys, so after five minutes every navigation pays a conditional-GET round trip for the stylesheet before first paint.

## Goal
First paint stops paying for uncompressed, short-cached assets.

## Outcome
Browsers that accept gzip or brotli receive compressed `app.css`/`app.js`; a repeat visit within a long window serves the assets from local cache with no revalidation request; a deploy that changes an asset is still picked up by clients.

## Why it matters
This is the largest wire-size win available — ~25KB and a blocking round trip off every cold or expired-cache page view, on every site. It matters most on high-latency clients, which is exactly where a demo gets judged.

## Discovery notes
- No new dependency needed for assets: the existing `assets` build step can emit `.gz`/`.br` siblings via `node:zlib`, and the already-present `@fastify/static` selects them with `preCompressed: true` (it handles `Accept-Encoding` and `Vary` itself).
- For the cache window: content-hashed filenames plus `immutable` long `maxAge` — the pattern the `/uploads` mount already uses because UUID names never change — with the hashed name reaching `head.ejs` via a small build-time manifest. A longer flat `maxAge` is the cheap intermediate if hashing feels heavy; ETag revalidation already bounds staleness at one round trip.
- Compressing HTML responses would mean `@fastify/compress` — a new dependency that needs the justification bar; assets-only precompression is the pragmatic cut.

## Related work
- BUG-008 (introduced the current cache headers and runtime asset copy)
- IMPRV-022 (placeholder images are the other page-weight driver)

## Working

- 2026-08-25 — re-validated: no response carries `Content-Encoding`
  (`app.ts:102` registers `@fastify/static` without `preCompressed`), the
  public registration serves `maxAge: '5m'`, `public/app.css` is 31,252 bytes
  and `public/app.js` 863 bytes.
- Cache-policy decision: content-hashed names + `max-age=31536000, immutable`,
  the `/uploads` pattern. The ticket's outcome needs both a long
  revalidation-free window and deploy pickup; a flat longer `maxAge` gives the
  window at the cost of stale assets for its whole length, hashing gives both.
  The hashed name reaches templates through `public/assets-manifest.json`,
  written by the assets build and read once in `buildApp`; a missing manifest
  falls back to the unhashed names so a dev container mid-build still renders.
  The unhashed `/app.css` and `/app.js` keep BUG-008's 5m policy — the
  registration-wide `maxAge` stays `'5m'`, `setHeaders` upgrades only names
  matching the hash pattern — so first paint stays stable across navigations
  whichever URL a page references.
- Compression: `node:zlib` `gzipSync`/`brotliCompressSync` in a new
  `app/cli/build-assets.ts` step appended to the npm `assets` script, emitting
  `.gz`/`.br` siblings of the hashed files; `preCompressed: true` on the
  public registration selects them and owns `Accept-Encoding`/`Vary`.
  `@fastify/compress` for HTML stays out per the ticket.
- Image path: the build stage gains `COPY src/public ./public` ahead of
  `npm run assets` so hashing sees `app.js`; the runtime stage copies the
  build stage's whole `public/` (manifest, hashed, compressed) in place of
  the two-copy split. Entrypoint already reruns `npm run assets` each start;
  the build script deletes stale hashed outputs so they do not accumulate.
- Generated files (`app.<hash>.css/js`, siblings, manifest) join `.gitignore`
  and `.dockerignore` beside the already-ignored `app.css`; `app.js` stays
  tracked as the source copy.
- Known trade-off (reviewer): `buildApp` reads the manifest once, so running
  `make assets` against an already-running `make up` container deletes the
  hashed files the live server's in-memory manifest points at — asset
  requests 404 until the dev container restarts. `node --watch` watches only
  `app/`, so the rebuild does not trigger a restart. Restart the dev stack
  after an out-of-band asset rebuild.
- Reviewer verdict: accept. Optional polish applied (alphabetized the
  `asset-manifest` import members in `app.ts`). Confirmed `docs/alignment.md`
  fixes no cache/compression shape, so php/rails owe no matching change.
