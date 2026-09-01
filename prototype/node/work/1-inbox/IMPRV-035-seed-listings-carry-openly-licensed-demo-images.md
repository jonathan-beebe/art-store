---
id: IMPRV-035
type: improvement
status: open
created: 2026-08-31
---

# IMPRV-035: seed listings carry openly licensed demo images

## Problem
Seeded listings render generated placeholder images
(`app/plugins/placeholder-images.ts`), so demos read as a mockup: every
card, tile, featured band, and configurator page shows synthetic fills
rather than photographs of the objects the listings describe.

## Goal
A fresh checkout demos as a believable handmade-goods marketplace.

## Outcome
Every seeded listing (or most, with gaps named) shows a fitting photograph
of its real-world object across the storefront, seller, and admin
surfaces. Every image is openly licensed, and each one's source page,
direct file URL, license, and author attribution (where the license
requires it) are documented in a manifest kept with the images — the
record this ticket exists to guarantee. The images never ship: they stay
out of the production image and out of git-tracked paths, and seeds fall
back to today's placeholders when the files are absent, so a checkout
without them still works.

## Why it matters
Owner call (2026-08-31): believable demo imagery, sourced from any
accessible open-license source, with sources documented for our records.
Demo credibility is the point of the seed data; license documentation is
the condition for using found imagery at all.

## Discovery notes
Sourcing is underway (2026-08-31): a curator is reading the seed inventory
on branch `node/php-alignment` and downloading matches into
`__local__/demo-images/node/` with `manifest.md` as the per-image source
and license record — Wikimedia Commons, Openverse, Pexels, Pixabay,
Unsplash as candidate sources; real-object photographs, product-photo
framing, no franchise fan art. Wiring suggestion: seeds reference the
staged files when present (gitignored path bind-mounted into the
container), placeholder fallback otherwise; the `listing_images`
position-0 row is the natural attachment point. The webp derivative
pipeline (FEAT-022) will later read these same originals.

## Related work
- branch node/php-alignment (listing_images, seeds, the surfaces these images feed)
- FEAT-022 (storefront images render as cached webp derivatives)
- app/plugins/placeholder-images.ts (the fallback that must keep working)
