---
id: FEAT-008
type: feature
status: open
created: 2026-08-22
---

# FEAT-008: Seed data and demo reset

## Problem
A fresh database has two admins and nothing else. Reviewers need a populated gallery, sellers with history, a customer with favorites and orders in each state, and admin data to look at, so the product can be judged without an hour of clicking.

## Goal
`make fresh` produces a database a reviewer can demo every page from in five minutes.

## Outcome
- Four verified sellers with shops and ~30 listings across media (most `for_sale`, some `draft`, a couple `sold`, one under a temporary removal), each with a placeholder image.
- One verified customer (`casey@example.com`) with favorites, view history, a cart, and orders in `paid`, `shipped`, and `delivered` states; one blocked customer; a few anonymous customers with page-view history.
- Ledger and payout rows consistent with those orders (held, released, paid out) built through the FEAT-003 actions, never by inserting ledger rows directly.
- Page-view counts for the last 14 days.
- `app/db/seed.test.ts` asserts the counts; README lists the seeded accounts.

## Why it matters
A seeded database is the difference between a demo and a tour of empty tables.

## Discovery notes
Port the catalog from `prototype/rails/src/db/seeds.rb` (titles, media, prices, shop names). Drive state through actions with a frozen clock so dates are deterministic. Keep FEAT-002's admin seed as the first step.
- Touch only `app/db/seed.ts`, `app/db/seed.test.ts`, README "Seeded accounts". FEAT-004/005/006 run in parallel — commit with an explicit pathspec. FEAT-007 adds seeded conversations itself.

## Related work
- `prototype/rails/work/3-done/FEAT-006-seed-data-and-demo-reset.md`
