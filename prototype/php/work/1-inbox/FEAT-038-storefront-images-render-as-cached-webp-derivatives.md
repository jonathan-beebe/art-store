---
id: FEAT-038
type: feature
status: open
created: 2026-08-31
---

# FEAT-038: storefront images render as cached webp derivatives

## Problem
Listing images are served as the uploaded originals: seller uploads store
the file with the listing (`listing_images`, position-ordered, lowest is
cover) and every storefront surface — cards, tiles, the featured band, the
detail page — renders the same full-size bytes. No resizing or format
optimization exists anywhere in the prototype.

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
weight and LCP suffer, and the tile/featured layouts amplify it. The
library boundary keeps the conversion reusable when the wiring later moves
to upload time.

## Discovery notes
Owner's design (2026-08-31): original stored with the listing; a rendering
library agnostic of page and lifecycle; request-path generation for the
initial prototype with upload-path wiring anticipated later; templates name
identity + size requirement; known cache dir checked first.

Container codec: the dev image's gd is configured
`--with-freetype --with-jpeg` only (`Dockerfile:21`) — no webp; the runtime
stage's `install-php-extensions gd` likely includes it. We control the
image: add webp support to the dev build and assert webp encode support in
the suite so dev and prod cannot diverge silently.

Library shape suggestion: a pure decision (preset + source dimensions →
target dimensions) separated from the encode adapter (gd). The closed
preset set is the security boundary — arbitrary WxH from a URL is a
cache-fill and CPU-amplification vector; presets are code, requests only
name them. Cache keys carry the source's content version (hash prefix or
updated_at) so replacement invalidates. Cache dir under the public root so
a hit is served by Caddy without the app in the loop (first miss through an
app route; try_files-style fallback is a candidate). Write derivatives to a
temp name and rename atomically — concurrent first misses may double-render
and that stays harmless. Cold-page cost: a first render of a 12-image grid
runs 12 generations across the recently downsized thread pool
(php/stateless-badge) — bounded, one-time per derivative; if it stings, run
the same library at upload time. Strip EXIF from derivatives; cap decode
dimensions before touching bytes (getimagesize); never rasterize SVG. WebP
to all clients, no Accept negotiation. A regenerate/backfill artisan
command per CLI conventions. Sibling: node FEAT-022, same behavior.

## Related work
- FEAT-025 (listing_images and the configurator data model)
- php/store-design (tile and featured-band layouts these derivatives feed)
- IMPRV-019 and branch php/stateless-badge (the thread-pool sizing the cold-page note references)
- node FEAT-022 (sibling ticket)
