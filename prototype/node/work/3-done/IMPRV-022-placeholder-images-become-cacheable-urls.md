---
id: IMPRV-022
type: improvement
status: resolved
created: 2026-08-25
---

# IMPRV-022: Placeholder images become cacheable URLs

## Problem
Every listing card without a photo regenerates its placeholder at render time: `placeholderImageDataUri` (`app/core/listings/placeholder-image.ts:37-59`) runs crc32 + SVG string building + base64 per card per render, and the ~1.5–2KB data URI is embedded in the HTML (`sites/shop/views/partials/listing-card.ejs:4`). Twelve photo-less cards add ~20KB of regenerated, browser-uncacheable markup to each storefront page — inflated 33% by base64 and shipped uncompressed (see IMPRV-020).

## Goal
Placeholder art is fetched once and cached, not re-rendered into every page.

## Outcome
A photo-less card references a small, stable URL whose image the browser caches per listing; page HTML shrinks by the embedded data-URI weight; the art shown per listing is identical to today, and pages remain plain `<img>` progressive enhancement.

## Why it matters
Page weight on photo-sparse catalogs — which is what the seed data mostly is — plus repeated per-render generation work. Low urgency on its own; it compounds with the compression and caching work.

## Discovery notes
- A GET route that regenerates the SVG deterministically from the listing slug/title, served with the same long-cache headers as `/uploads`, keeps the pure generator exactly where it is and shrinks each card to a ~60-byte URL.

## Related work
- IMPRV-020 (asset compression and cache policy)

## Working

Re-validated 2026-08-25 on `node/performance` (after IMPRV-020/021): the problem
stands. `listingImageSource` (`core/listings/placeholder-image.ts:57-59`) is the
single choke point — shop views (`listing-card.ejs:4`, `listing.ejs:3`,
`cart.ejs:27`) and seller routes (`sites/seller/routes/listings.ts`, six call
sites) all go through it, so changing its fallback covers every use site.

Decisions:
- Route: root-level `GET /placeholders/:title` in a new
  `app/plugins/placeholder-images.ts`, registered like `healthCheck` — outside
  every site, because shop and seller both render placeholders. The title rides
  in the path URL-encoded; the SVG derives from the title, so the URL is the
  cache key and a renamed listing gets a new URL.
- `isAssetPath` (`http/asset-manifest.ts`) treats `/placeholders/` as an asset:
  no session mint, no request-log lines — same footing as `/uploads/`.
- Cache headers set in the route (dynamic route, no `setHeaders` mount):
  `public, max-age=604800, immutable` matching `/uploads/`, plus explicit
  `content-type: image/svg+xml`. The global `securityHeaders` hook already adds
  nosniff and the CSP to every response.
- Escaping verified: `escapeHtml` (`placeholder-image.ts:14-16`) escapes
  `& < > " '`; the title is the only string interpolated into the SVG (attribute
  and text-node positions), and the hostile-title test pins it. A served SVG
  document cannot carry script from the title, and CSP `script-src 'self'`
  backstops it.
- `placeholderImageDataUri` is deleted with the change; `data:` leaves
  `img-src` in the CSP — the placeholder was its only justification.
