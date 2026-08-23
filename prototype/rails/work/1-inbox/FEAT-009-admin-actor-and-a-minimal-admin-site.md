---
id: FEAT-009
type: feature
status: open
created: 2026-08-23
---

# FEAT-009: Admin actor and a minimal admin site

## Problem
The Rails prototype has two actors, `Seller` and `Customer` (`src/app/models/seller.rb`, `src/app/models/customer.rb`); `MagicLink.actor_type` (`src/app/models/magic_link.rb:8`) admits only `seller` and `customer`, and `src/config/routes.rb` has no `/admin` namespace. The marketplace brief has platform operators who message sellers and customers (support threads), and the Node prototype ships an admin site that does so. With no admin actor the `admin_seller` and `admin_customer` conversation kinds have nobody on the other side.

## Goal
A platform operator can sign in and reach any seller or customer from the admin site, so support threads have a counterpart.

## Outcome
An admin signs in by magic link at `/admin/login` with a seeded address and lands on `/admin`, which lists sellers and customers; `/admin/sellers/:id` and `/admin/customers/:id` show that account; an address with no admin row is refused at sign-in; the admin layout carries the same debug alert and flash partials as the other two; `make fresh` seeds one admin; every existing test still passes and the new controllers and model have mirrored tests.

## Why it matters
Two of the four conversation kinds the messaging feature needs are admin ↔ seller and admin ↔ customer. This is the smallest admin site that gives them a home; moderation, payouts from the admin side, and the rest of Node's admin site stay out.

## Discovery notes
`Seller.claim` creates an account on first sign-in; admins are seeded only, so an `Admin.claim` (or `Admin.find_by_verified_email`) that refuses an unknown address is the safer shape — `Auth::MagicLinksController` (`src/app/controllers/auth/magic_links_controller.rb`) holds `HOME_PATHS`/`LOGIN_PATHS` tables and a `sign_in` branch that will grow a third arm. `SellerAuthentication` (`src/app/controllers/concerns/seller_authentication.rb`) and `Seller::BaseController` are the template for `AdminAuthentication` / `Admin::BaseController`; `Admin` is a model class, so the namespace needs the compact `class Admin::XController` form the architecture doc describes for `Seller::`. `Admin` wants `include EmailAddress`, `has_many :notifications, as: :recipient`. A `layouts/admin.html.erb` in a third visual register (the seller portal is dense neutral; pick something that reads as "operator console", e.g. a dark slate header) with a nav of Dashboard, Sellers, Customers. `README.md` "Seeded accounts" gains the admin row. Keep the dashboard to lists with links; the seller page can show shop name, email, listing count; the customer page email/name, order count. No moderation, no blocks.

## Related work
- FEAT-002 (magic-link identity)
- RFCTR-004 (identity on the models)
- RFCTR-010 (polymorphic notification recipient)
- prototype/node/work/3-done/FEAT-006-admin-site.md
