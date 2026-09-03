---
id: FEAT-049
type: feature
status: resolved
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

## Working

Two stages. Stage A (funnels as admin data, the query, the editor, the
home's tiles):

- `b43f2ad2` funnels are admin data: a name and an ordered list of event
  names
- `693b6b32` a funnel is computed from its step list, counting sessions
  per step
- `84c49db1` admins create, edit, reorder, and remove funnels
- `6fffc5bc` the analytics home shows a tile per funnel with its
  end-to-end conversion

Stage B (DSGN-009's accepted drawing, the detail page, seed
confirmation, docs):

- `a5f478ed` the funnel draws as a shared-borders grid with share bars,
  the previous range, and the largest drop
- `8a62f7a0` the funnel page and the listing and seller pages draw the
  accepted funnel
- `9f66eb63` the activity plan is asserted to open checkout before it
  places an order
- `371d736e` the funnel docs and alignment describe the shipped drawing

**The sessions unit.** Every step — visitors included — counts
`count(distinct session_id)` over the events that qualify for it
(`Funnel::sessionsByName()`/`visitorTotals()`), so a step is always a
subset of the one before it and no bar can ever exceed its container.
The storefront funnel's own `Viewed a listing` step reads exactly equal
to `Visitors` in the seeded data: every seeded session's script views at
least one listing, so the two step counts coincide rather than one
exceeding the other.

**The editor's form-post ops.** `/admin/funnels`'s create/edit forms have
no JavaScript: "Add step", "Remove", "Move up", and "Move down" all post
back to the same `store`/`update` route carrying an `op`;
`FunnelStepListOp::apply()` applies it to the working step list and the
controller re-renders the form with that list rather than saving. Only
`op=save` (the form's default submit) runs `FunnelDefinition::of()` and
persists.

**Decisions.**

- Favorites renders as a side count on the viewed step (`docs/funnel.md`'s
  Primary option), never a step of its own — settled in stage A, restated
  as fact rather than an open option in stage B's docs pass.
- The component's own legend ("this range" / "previous range") sits in a
  small header row above the grid, right-aligned; every calling page
  keeps its own `<h2>` above that, so the component never repeats a
  title.
- Grid columns follow the step count (`grid-cols-1 sm:grid-cols-3
  lg:grid-cols-{1..7}`), capped at 7; a longer funnel wraps to a second
  row rather than growing narrower cells.
- The "largest drop" badge reuses `x-admin.status-badge` with `tint="warn"`
  rather than a bespoke amber pill, so the funnel's badge and every other
  admin warning badge stay one component.
- `work/journal.md`'s `## Log` was audited for the rebase-interleaved
  entries the ticket flagged; every one of its 360 entries reads
  newest-first by timestamp already, so no reordering was needed.

**Live numbers.** After `make fresh && make seed-activity` (524
customers, 31 orders, 3581 analytics events) and a curl walk signed in as
the seeded admin (`jonathan-beebe@outlook.com`, session-delivered magic
link) against `/admin/analytics/funnels/{storefront funnel id}?range=30`:

| Step             | Count | Note              |
| ----------------- | -----: | ------------------ |
| Visitors           | 344   |                    |
| Listing views       | 344   | 100% of visitors   |
| Cart adds           | 142   | 41% of listing views |
| Checkouts opened    | 27    | 19% of cart adds — largest drop |
| Orders placed       | 25    | 93% of checkouts opened |
| Orders paid         | 19    | 76% of orders placed |

Every step reads at or below the one before it; the badge lands on
"Checkouts opened", the step with the lowest rate (19%), matching the
accepted design's own placement for the store-wide funnel. The grid drew
`lg:grid-cols-6` for the six-cell storefront funnel, both bars at the
tooltipped counts (e.g. `title="this range 142 · previous 44"`), and the
h1 carried the five-step chain as mono chips with a breadcrumb back to
`/admin/analytics?range=30`.

**Gate.** `make precommit`: lint (Pint + PHPStan) clean, 4025 tests
passed (33021 assertions).
