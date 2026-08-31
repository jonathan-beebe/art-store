---
id: DSGN-008
type: design
status: open
created: 2026-08-31
---

# DSGN-008: the design system is audited, accurate, and complete

## Problem

`/design-system` (`resources/views/shop/design-system/` — atoms, colors,
typography, components, layouts, mobile, relationships, specimens/) is the
living reference for the Warm Craft language, and the storefront has moved
under it: DSGN-007's home redesign (featured band, golden-ratio tile,
media tile row with drawer and gallery panel, three-then-nine listing
sets, wayfinding footer), the browse-media sheet, and newer tokens such as
`photo-scrim` (commit 4aaf8d9). Spot checks show much of this is captured,
but no systematic pass has verified the reference against the shipped
product since those changes landed — its accuracy and completeness are
currently unaudited, not known-good.

## Goal

The design system is verified against the shipped storefront and stands as
a known-accurate, complete reference.

## Outcome

A full sweep of the shipped storefront, seller, and admin surfaces against
every `/design-system` page has been performed and its findings resolved:
every component and token the product wears has a live, matching entry or
specimen; no design-system section demonstrates a look the product no
longer wears; and the audit's scope and result are recorded in the
ticket's Working notes so "accurate and complete" has a dated basis
rather than an assumption.

## Why it matters

A living reference is only worth consulting while it is trusted; an
unaudited one silently decays until new work copies stale patterns.
Verifying it now — right after a heavy design push — is the cheap moment,
while the changes and their authors' intent are fresh.

## Discovery notes

Advisory.

- The `design-system-sweep` skill exists for exactly this: sweeping
  project components not defined in the design system and adding them.
- Walk the rendered pages, not just the templates — a stale cached copy
  already caused one false alarm here; audit against the live server
  (`make up`, port 8000).
- Candidate check-list from a grep pass (verify visually, not
  authoritative): media-gallery-panel, `x-ui.section-header`/`x-ui.chip`,
  the wayfinding footer, the home page anatomy on the layouts page, the
  `--ui-photo-scrim` token on the colors page.
- Cheap cleanup while in there:
  `resources/views/components/tile.blade.php:12`'s comment cites
  "(DSGN-007)" — the house rule says code never references tickets.

## Related work

- DSGN-007 (commit fb83ab4) — the home redesign, the largest recent shift
- Commit 0004902 — the theme system and the design-system page's origin
- Commit 4aaf8d9 — the photo-scrim token
