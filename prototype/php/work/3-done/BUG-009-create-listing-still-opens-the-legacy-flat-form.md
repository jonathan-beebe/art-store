---
id: BUG-009
type: bug
status: done
created: 2026-08-27
---

# BUG-009: Creating a listing still opens the legacy flat form

## Problem
`GET /seller/listings/create` renders
prototype/php/src/resources/views/seller/listings/create.blade.php, which
includes form.blade.php — the pre-DSGN-002 flat form (title, description,
dimensions, price, quantity, category, single image in one screen).
DSGN-002 retired that pattern from the editor: the hub is summary rows
with detail screens (Basics, Images), and DSGN-002's implementation
record notes create was deliberately left flat. The reporter rejects
that carve-out: the old form is exactly what DSGN-002 set out to remove.

## Goal
Creating a listing feels like the same product as editing one.

## Outcome
A seller creating a listing never meets the legacy flat form; the create
flow presents the redesigned editing experience (the exact shape — a
minimal Basics-style start that lands on the row hub, or the hub itself
in a new-listing state — is the maker's call), and form.blade.php has no
remaining consumer.

## Why it matters
The first screen a new seller ever sees is the one screen still speaking
the old design; every mental-model benefit DSGN-002 bought starts one
screen too late.

## Discovery notes
Reported live while walking the new editor. UpdateListing already
tolerates absent price/quantity fields and ListingRequest's image rule is
create-only (DSGN-002 phase E note) — most of the request-side room for a
slimmer create already exists.

## Related work
- prototype/php/work/3-done/DSGN-002-retire-legacy-form-unify-editor-into-rows.md

## Working

2026-08-27 — Design owned by DSGN-003 (guided new listing, three pricing
on-ramps); this ticket closes with DSGN-003's implementation.

2026-08-27 — Closed by DSGN-003's implementation on php/item-configurator:
`form.blade.php` is deleted, and `GET seller/listings/create` now opens
the guided, three-shape on-ramp flow rather than the legacy flat form.
