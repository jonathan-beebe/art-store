---
id: FEAT-049
type: feature
status: open
created: 2026-09-02
---

# FEAT-049: admins define funnels as ordered event names

## Problem

The one funnel the admin has is fixed in code: `App\Analytics\Admin\Funnel`
names its seven steps and `x-admin.analytics.funnel` draws that list.
Every other path through the store an operator might want to watch — a
favorite that becomes an order, a checkout that gets paid, a listing view
that ends in a support conversation — needs a developer to add a query
and a view. The analytics vocabulary is a closed enum
(`App\Domain\Analytics\AnalyticsEventName`), so a funnel is only an
ordered list of those names, and nothing in the system lets an admin
write one down.

## Goal

An admin defines a funnel by naming its steps in order, and it appears
on the analytics home and drills in like the built-in one.

## Outcome

An admin can create, edit, reorder, and remove funnels in the admin, each
a name plus an ordered list of two or more event names drawn from the
analytics vocabulary, with an unknown or repeated name refused at save
time; the analytics home shows one tile per funnel with its end-to-end
conversion for the chosen range (last step as a share of the first) and
the change against the range before; tapping a tile stacks that funnel's
detail page, drawn by the funnel component DSGN-009 delivers, with the
range control, every step's count, its share of the first step and of
the step before it, and the previous range; the built-in storefront
funnel exists as one of these definitions, seeded on `make fresh`, so the
home shows it alongside any the admin adds and it is edited the same way;
a funnel counts one unit at every step, so a step never exceeds the one
before it; a funnel with no events in the range renders with zeros and no
error; the routes are named in the admin docs and the alignment
contract; the suite stays green.

## Why it matters

A funnel is a question about a path. The operator who asks it should not
wait for a release to see the answer, and the developer should not add a
query class per question. Once steps are data, every future event added
to the vocabulary becomes a possible step with no further code.

## Discovery notes

- Definitions are admin configuration: a `funnels` table in the app
  database (`id` prefixed ULID, `name`, `slug` for the route, a `steps`
  JSON list of event-name strings, `position` for tile order,
  timestamps), an Eloquent model, a factory, and an admin resource
  controller in the existing admin shape (`Admin\ListingController` and
  the customer-block form are the patterns; a form request validates the
  steps against `AnalyticsEventName::cases()`). The analytics store is
  never joined; a funnel definition only supplies names to the query.
- `App\Analytics\Admin\Funnel::forRange()` becomes a function of a step
  list: give it the ordered names and it returns `FunnelStep`s, counting
  distinct sessions per step within the range (the unit DSGN-009 fixes).
  The built-in seven-step funnel is then a seeded row, and the three
  scoped variants (listing, seller) keep working by passing the same
  list with a scope.
- Steps in the editor: a select per row from the vocabulary with the
  enum's `pluralLabel()`, add and remove row, move up and down — a form
  post per action keeps it server-rendered like the rest of the admin;
  `docs/alignment.md` §5's "an unrecognised value answers 400" rule
  applies to the step names.
- Routes: `admin.funnels.index|create|store|edit|update|destroy` under
  `/admin/funnels`, and `admin.analytics.funnels.show` at
  `/admin/analytics/funnels/{funnel}` for the detail page; the home's tile
  row sits above the events table where the fixed funnel sits today.
- Tile conversion for a range with zero first-step sessions is "—", not
  a division.
- Sellers do not see funnels; the seller dashboard keeps its own numbers.
- Node and Rails owe parity once the routes land in §5.

## Related work

- FEAT-046 — the fixed funnel and its query
- DSGN-009 — the funnel drawing and the step-list component contract
- FEAT-047 — sessions as the unit
- FEAT-045 — the analytics home the tiles land on
