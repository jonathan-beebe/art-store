---
id: DSGN-007
type: design
status: open
created: 2026-08-30
---

# DSGN-007: The storefront home is an invitation to explore

## Problem

`shop/home.blade.php` is a heading, a media row, a strip of text
category pills, and then the whole catalogue in one grid. It shows
everything and recommends nothing: a first-time visitor gets no way in
beyond scrolling, the categories read as chrome rather than places worth
going, and nothing on the page says what this store is or who makes the
work.

## Goal

A visitor who has never seen the store finds somewhere to start within
one screen.

## Outcome

The home page opens with one featured piece across the full viewport
width — its photograph on the left two thirds, its story, price and a
way in on the right third — configured by hand and unchanged until it is
configured again. Below it the page reads as a sequence of invitations:
the mediums as image tiles, three newly listed pieces, the categories as
image tiles, then nine more pieces. It ends with a light footer that
answers "where do I start" — a search field and every medium and
category — rather than a sitemap.

Medium and category tiles are the golden ratio, 1.618 to 1. Opening the
media drawer reveals the remaining tiles into the same grid at the same
size, so the rows read as one surface rather than a second component,
and it pushes the page down rather than covering it.

The home page no longer renders the full catalogue; the browse and
search paths own that. Every new piece of vocabulary this introduces is
in the design system at `/design-system` with a specimen.

## Why it matters

The store sells one-of-a-kind work from named makers. A wall of
thumbnails sells none of that, and the visitor who does not already know
what they want has nothing to do but scroll.

## Discovery notes

Approved design canvas (the full page, and the drawer open):
https://claude.ai/code/artifact/d3cd3521-d2af-4933-8b5f-df7a170c4d5e
Reference, not a pixel spec. Its image blocks stand in for the real
seeded photographs, drawn from the theme's tint tokens.

Advisory:

- The featured piece is configured, per the human: the simplest shape
  that satisfies "static until we configure a new one" is a config entry
  naming one listing slug or one category path, resolved at render. It
  needs an honest answer when what it names is gone or no longer for
  sale — degrade to the section being absent rather than a broken card
  or a fabricated substitute.
- The existing `shop.partials.media-tile-row` and
  `media-gallery-panel` already carry the medium picker and its drawer;
  this reshapes them rather than replacing them. `listing-card` is the
  existing product card.
- JS-off, as everywhere on the shop.
- The bottom `sm:` sheet path for the medium picker on phones already
  ships and stays.

## Related work

- FEAT-034 — first-class browse and search paths (which own the full
  catalogue this page stops rendering)
- `prototype/php/docs/theming.md` — Warm Craft tokens and
  `/design-system`
