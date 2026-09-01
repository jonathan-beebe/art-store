---
id: FEAT-022
type: feature
status: open
created: 2026-08-31
---

# FEAT-022: storefront images render as cached webp derivatives

## Problem
Listing images are served as the uploaded originals: the seller upload path
stores the size-capped original (`app/sites/seller/listing-image-upload.ts`,
format sniffed by `core/listings/image-format.ts`) and every storefront
surface renders those same bytes. No resizing or format optimization exists
anywhere in the prototype.

## Goal
Every storefront image is served as an optimized, right-sized derivative,
without any page or lifecycle owning the conversion.

## Outcome
Uploads store the original with the listing, unchanged. A standalone
rendering library — independent of any page render or request lifecycle —
takes an image and a size requirement and produces the resized, optimized
(WebP) result; it can be driven from the upload path or the request path
without change. In the shipped wiring, templates name an image by its
identity plus a size preset from a closed, named set; the first request for
a missing derivative renders it into a known cache directory and serves it,
and later requests serve the cached file. A replaced original never serves
a stale derivative. A request naming a size outside the preset set or an
unknown identity mints no file. `make check` stays green.

## Why it matters
Storefront pages ship multi-hundred-KB originals into ~300px slots — page
weight and LCP suffer, and the lane-A tile and featured layouts amplify it.
The library boundary keeps the conversion reusable when the wiring later
moves to upload time.

## Discovery notes
Owner's design (2026-08-31): original stored with the listing; a rendering
library agnostic of page and lifecycle; request-path generation for the
initial prototype with upload-path wiring anticipated later; templates name
identity + size requirement; known cache dir checked first.

Codec: `sharp` (libvips) — the small, boring, widely-trusted dependency the
doctrine's bar permits; it auto-rotates EXIF, strips metadata, and caps
decode via `limitInputPixels`. Library shape suggestion: a pure decision
core (preset + source dimensions → target) with the encode as a shell
adapter, matching the existing sniff/store split in the upload path. The
closed preset set is the security boundary — arbitrary WxH from a URL is a
cache-fill and CPU-amplification vector; presets are code, requests only
name them. Cache keys carry the source's content version (hash prefix or
updated_at) so replacement invalidates. Cache dir under the static root so
a hit is served by fastify-static without app code (first miss through an
app route). Write derivatives to a temp name and rename atomically —
concurrent first misses may double-render and that stays harmless. Never
rasterize SVG; WebP to all clients, no Accept negotiation. A
regenerate/backfill CLI command per conventions. Node stores one image per
listing today; the configurator lane on `node/php-alignment` brings
`listing_images` parity — sequence with or after it, or ship on the single
image and extend. Sibling: php FEAT-038, same behavior.

## Related work
- branch node/php-alignment lane A (tile and featured-band layouts these derivatives feed)
- app/plugins/placeholder-images.ts (the existing image-serving seam)
- php FEAT-038 (sibling ticket)
