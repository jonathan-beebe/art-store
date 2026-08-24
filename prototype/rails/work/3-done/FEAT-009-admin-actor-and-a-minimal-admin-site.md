---
id: FEAT-009
type: feature
status: resolved
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

## Working

### Verified before changing anything

`MagicLink.actor_type` admitted `seller` and `customer`; `config/routes.rb` had
no `/admin`; `Auth::MagicLinksController#sign_in` was an `if link.seller?`
two-arm branch; `Seller::BaseController` carried the compact-class comment the
architecture doc calls for. Baseline suite: 527 runs, 100% line coverage.

### Decisions

- **Where the unknown address is refused.** At verification, in
  `Auth::MagicLinksController`, not at the point the link is asked for. The
  verify side is what hands out a session, so it is where the rule belongs, and
  refusing at the form would answer "is this address an operator?" to anyone who
  types one. `Admin.claim` returns nil for an address no row holds, `sign_in`
  returns nil with it, and `show` refuses to `/admin/login` with "That address
  does not reach the admin site." The link is consumed either way, so a refused
  link cannot be replayed. The Node prototype refuses earlier (`admits` on the
  sign-in route); this is the deviation and the reason for it.
- **`sign_in` became a `case` over `actor_type`** returning the actor now in the
  session. The three arms read the same and the nil is what the refusal hangs
  on.
- **`Admin.claim` over `Admin.find_by_verified_email`.** The three sign-in arms
  then read `Actor.claim(link.email)`. The comment on the method carries what is
  different about it: seeded, never created.
- **Nav "Sellers" and "Customers" are anchors into the dashboard**
  (`admin_root_path(anchor: "sellers")`). The ticket scopes the site to one list
  page plus two detail pages, so index routes would be a second copy of the
  dashboard.
- **The admin site knows verified customers only.** The dashboard lists
  `Customer.verified` and `Admin::CustomersController#show` finds through the
  same scope, so an anonymous row 404s rather than rendering a page with no
  address on it. Every storefront visitor has a `customers` row; listing them
  would bury the accounts an operator can actually contact.
- **`Customer#display_name`** added to mirror `Seller#display_name`: name, else
  the local part of the address, else `Visitor #<id>`. The admin pages need a
  label for a customer who verified an address without giving a name.
- **Third visual register:** slate, dark body (`bg-slate-900`) with a
  `bg-slate-950` header, against the seller portal's light neutral and the
  storefront's white.

### Deviations from the ticket and the brief

- The brief names `Auth::AdminSessionsController` at `/admin/login`; that is what
  landed, with the refusal in `Auth::MagicLinksController` as the ticket asks.
- README changes went past "Seeded accounts": the coverage line (527 → 567
  runs), the first-run URL list, and the Layout block's controller / layout /
  routes lines, which named two sites. FEAT-014 owns the docs sweep;
  `docs/architecture.md` still describes two sites and two layouts and is left
  for it.

### Left alone

Messaging (FEAT-010 onward), notifications on the admin site, moderation,
payouts from the admin side, and every other page of Node's admin site.
`Admin` has `has_many :notifications, as: :recipient` and nothing renders them
yet.

### Verification

- `docker compose run --rm -e COVERAGE_MIN=100 app bin/rails test`: **567 runs,
  1704 assertions, 0 failures, 0 errors**, line coverage **975/975 (100.00%)**.
  Baseline was 527 runs.
- `make fresh`: `Seeded 1 admin, 4 sellers, 29 listings, 1 customers, 3 orders.`
- curl walk against `rails-app-1`: `/admin` signed out → 302 `/admin/login`;
  `/admin/login` renders in the admin layout; POST → link in the debug alert;
  following it → 302 `/admin`; the dashboard lists 4 sellers and 1 customer;
  `/admin/sellers/1` shows Terra & Glaze Ceramics, `maya@example.com`, 7
  listings; `/admin/customers/1` shows Casey Whitfield, `casey@example.com`, 3
  orders; `/admin/sellers/999` → 404; a link for `stranger@example.com` → 302
  `/admin/login` carrying the refusal alert.

### Found outside this ticket

The long-running dev container answered 500 with `NameError (uninitialized
constant MagicLinkSender::MagicLinkMailer)` on every magic-link POST, seller
included, until it was restarted — a stale autoload state after ~19 hours up,
not a code fault. `docker compose restart app` cleared it.
