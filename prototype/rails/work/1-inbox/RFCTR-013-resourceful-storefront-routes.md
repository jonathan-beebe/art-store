---
id: RFCTR-013
type: refactor
status: open
created: 2026-08-22
---

# RFCTR-013: Resourceful storefront controllers behind the existing URLs

## Problem
`src/config/routes.rb` maps storefront verbs to custom actions: `carts#add`/`carts#remove`, `favorites#toggle`, `order_payments#show/create`, `notifications#update` for marking read, `delivery_confirmations#create` with an `:id` that is a fulfillment. The seller side already uses nested singular resources (`listing_statuses`, `shipments`, `notification_reads`).

## Goal
Every storefront controller is a resource with only the seven standard actions.

## Outcome
Cart lines, favorites and read-marks are `create`/`destroy` on their own controllers; the public URL paths and the HTML forms that post to them are unchanged; the storefront integration tests and the smoke test pass without edits to their request lines.

## Why it matters
Custom actions are where controllers grow; the seven-action constraint is the convention the rest of the app already follows.

## Discovery notes
Explicit `post "cart/:slug" => "cart_items#create"` lines keep the paths while the controllers become resources. The favorite toggle needs the button to know whether to `create` or `destroy`; the listing page already knows `@favorited`. Lowest priority of the set; URLs are user-facing, so keep them byte-identical.

## Related work
- RFCTR-006
