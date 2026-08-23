---
id: RFCTR-011
type: refactor
status: open
created: 2026-08-22
---

# RFCTR-011: Dissolve the Domain namespace and the custom autoloader

## Problem
`src/config/application.rb` removes `app/domain` from the eager-load paths and re-pushes it under a `Domain` namespace in a custom initializer; `app/domain`, `app/actions`, `app/delivery` and `app/support` are directories Rails does not have, and every status string is reached through a constant (`Domain::Orders::OrderStatus::PAID`) where the `enum` already provides `Order.paid` / `order.paid?` / `"paid"`.

## Goal
The application tree is the stock Rails tree and the models are the domain.

## Outcome
`config/application.rb` has no autoloader customisation; `app/domain`, `app/actions`, `app/delivery` and `app/support` do not exist; value objects that survive (money, payout period, page, placeholder image, fake card) live in `app/models` as plain Ruby classes; status references use the enum API or the bare string; `bin/rails zeitwerk:check` passes; the suite passes unchanged; `docs/architecture.md` describes the resulting layers.

## Why it matters
The custom namespace is the single largest thing a Rails developer has to learn here, and it exists only to host the service/domain split the other refactor tickets remove.

## Discovery notes
This is the closing ticket once RFCTR-004 through RFCTR-010 have emptied the directories. The SimpleCov groups in `test/test_helper.rb` name the old directories.

## Related work
- RFCTR-004
- RFCTR-005
- RFCTR-006
- RFCTR-007
- RFCTR-008
- RFCTR-009
- RFCTR-010
