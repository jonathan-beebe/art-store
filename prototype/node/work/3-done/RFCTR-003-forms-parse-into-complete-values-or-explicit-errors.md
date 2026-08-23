---
id: RFCTR-003
type: refactor
status: resolved
created: 2026-08-23
---

# RFCTR-003: Forms parse into complete values or explicit errors

## Problem
Several form and boundary types validate without parsing, throw for
expected business cases, or carry a cast where a narrower type would need
none.

**`isCheckoutComplete` validates but does not parse.**
`app/core/shop/checkout-form.ts:20-48`; `app/sites/shop/routes/checkout.ts:88-113`
— `parseCheckoutForm` returns `CheckoutForm` with `email: string` and a
`ShippingAddress` whose required fields are `string`, blanks included.
`isCheckoutComplete(form): boolean` then answers whether those strings are
non-empty, and the type is identical on both sides of the check.
`placeOrder(…, { shipping: form.shipping })` is reachable with blanks the
moment someone reorders the guard.

**The same "validate then separately parse" split exists in three more
form paths.** `app/core/listings/listing-draft.ts:54` +`:83`
(`listingDraftErrors` / `parseListingDraft`, called in that order at
`app/sites/seller/routes/listings.ts:107,115` and `:141,155`);
`app/core/messaging/faq-draft.ts:28` +`:37` (`app/sites/seller/routes/faqs.ts:68,76`
and `:97,103`); `app/core/orders/shipment-details.ts:7` +`:15`
(`app/sites/seller/routes/orders.ts:88-89`). Each pair is two functions
over the same wide `…Fields` type, one answering "what is wrong" and one
converting, with correct ordering as convention only. Called out of
order, `parseListingDraft` yields `quantity: NaN` and `parseDollars`
throws a bare `Error` out of core (`app/core/money.ts:65`). Separately,
`priceError` (`listing-draft.ts:36`) rejects `$249` and `1,234.00` while
`parseListingDraft` (`:89`) hands the same field to `parseDollars`, which
accepts both — two grammars for one field, disagreeing.

**Core throws `TransitionError` for expected business cases.**
`app/core/transition-error.ts`; throws at
`app/core/listings/listing-status.ts:24`,
`app/core/orders/order-status.ts:41`,
`app/core/orders/fulfillment-status.ts:24`, plus seven action-level
throws (`app/actions/messaging/post-message.ts:30,75,77`,
`app/actions/moderation/block-customer.ts:28`,
`app/actions/moderation/remove-listing.ts:31`,
`app/actions/moderation/lift-listing-removal.ts:22,24`,
`app/actions/moderation/lift-customer-block.ts:17`); caught at seven
route sites. "A seller pressed *put on sale* on an archived listing" and
"this account is blocked" are ordinary outcomes, and they travel as
exceptions. `changeListingStatus` is typed `Promise<Listing>`; nothing in
the signature says it can refuse, so the seven `try`/`catch` blocks are a
convention the compiler cannot enforce and a missed one is a 500.

**Casts and dead-weight guards.** `errors: {} as ListingDraftErrors`
(`app/sites/seller/routes/listings.ts:100,:130`) asserts nothing —
`ListingDraftErrors` is `Partial<Record<…, string>>`, so `{}` is already
assignable. `Object.fromEntries(...) as …` recovers key types in three
places (`listing-draft.ts:65`, `faq-draft.ts:34`,
`app/db/seed-page-views.ts:36`). `platform-tallies.ts:23,67-71` declares
`type CountedStatus = { status: string; count: number }`, deliberately
discarding the union Kysely already returns, then `asTallies` recovers it
with `row.status as Status` — an unchecked cast to an unconstrained type
parameter, so `asTallies<OrderStatus>(listingRows)` compiles.

**Runtime guards for states the type system already excludes.**
`app/core/payments/decline-reason.ts:11-14`: `DECLINE_MESSAGES` is
`Record<DeclineReason, string>`, so `DECLINE_MESSAGES[reason]` is
`string` and the `=== undefined` branch is unreachable; the test reaches
it with `declineMessage('stolen_card' as never)`.
`app/core/listings/listing-stock.ts:48-49`: `stockAfter`'s `default:` on
an exhaustive `StockChange` union is likewise unreachable, tested with
`stockAfter('reserve' as never, …)`. `app/core/listings/listing-status.ts:15-16`
and `app/core/orders/order-status.ts:32-33`: `canTransitionListing`
annotates `const allowed: readonly ListingStatus[] | undefined` and
writes `(allowed ?? [])` on a total `Record`.

**`satisfies` is never used; roughly 25 lookup tables are annotated and
widened.** `app/core/listings/listing-status.ts:6`,
`app/core/orders/order-status.ts:17`,
`app/core/orders/fulfillment-status.ts:6`, `app/core/auth/actor-type.ts:14`,
`app/core/messaging/conversation-kind.ts:18,29`,
`app/core/messaging/conversation-path.ts:5`,
`app/plugins/identity.ts:13,158,193`, `app/plugins/unread-messages.ts:21`,
`app/sites/auth/index.ts:13`, `app/delivery/magic-link-delivery.ts:25`,
and roughly 12 more. Every constant table is
`const X: Readonly<Record<K, V>> = { … }`, which checks the keys and
throws the literal value types away — `ACTOR_SITES.seller.loginPath` is
`string`, not `'/seller/login'`.

**Aggregates are asserted `<number>` and then re-coerced with
`Number()`.** The assertions span
`app/sites/admin/queries/page-view-report.ts:32,47,68`,
`app/sites/admin/queries/platform-tallies.ts:31,40,47,54`,
`app/sites/admin/queries/listing-event-tallies.ts:15`,
`app/sites/admin/queries/seller-accounts.ts:85`,
`app/sites/shop/queries/find-storefront-listings.ts:67`,
`app/actions/carts/current-cart.ts:16`; the coercions span a dozen more
sites. `fn.countAll<number>()` and `fn.sum<number>('count')` are type
assertions, not checks — every consumer writes `Number(row.count)`, and
`page-view-report.ts:73` writes `Number(counted.count ?? 0)`, which
contradicts the `<number>` it just asserted by admitting the value can be
null.

**`AppConfig` is hand-maintained beside the env schema.**
`app/config.ts:7-14` and `:20-41` — the `AppConfig` type lists six
fields, `environmentSchema` lists the same six, and `loadConfig` maps
between them by hand (a real SCREAMING_CASE → camelCase transformation, so
not pure duplication, but a seventh setting requires three edits).

**Money is a bare `number`.** `app/core/money.ts:5`
(`export type Cents = number`); money columns in
`app/db/commerce-schema.ts` typed `number`, not `Cents`.
`multiplyCents(amount, factor)` takes two `number`s in a fixed order and
defends the ordering with a runtime `assertIntegerAmount` throw rather
than a compile-time guarantee.

## Goal
Every form parses into a complete value or an explicit error — never a
value the caller must separately validate — and `Cents` is a type the
compiler enforces rather than a documentation alias.

## Outcome
- `parseCheckoutForm`, `parseListingDraft`, `parseFaqDraft`, and
  `parseShipmentDetails` each return
  `{ ok: true; value } | { ok: false; errors }`.
- One price grammar is used by both the error-checking and the parsing
  side of a listing draft.
- `Cents` is a branded type constructed only by `parseDollars` or the
  column reader.
- Aggregates funnel through one `toCount` function instead of a scattered
  `Number()` at every read site.
- Lookup tables use `satisfies` instead of a widening annotation.
- `AppConfig` is inferred from the environment schema instead of
  hand-maintained beside it.
- The four remaining non-`as const` casts described above are gone.

## Why it matters
Parse, don't validate: a value that still needs a separate completeness
check is not parsed. Illegal states unrepresentable applies directly to
`CheckoutForm`, the draft/FAQ/shipment "wide fields" types, and bare
`number` standing in for `Cents` — each permits a state the domain does
not allow. Core returning explicit results rather than throwing for
expected business cases is the standard `TransitionError`'s call sites
violate today: **`TransitionError` is for user-triggerable, expected
refusals — a stale form, a status change that is no longer possible.
`RangeError`/`TypeError` are for programmer error only.** This line is
currently crossed the other direction by `stockAfterSale` and
`quantityWithinStock`, which throw `RangeError` for what are actually
expected business cases (a listing sold out from under a stale cart) —
that specific violation is BUG-003's concern, not this ticket's; this
ticket's job is establishing and applying the general doctrine line plus
the remaining parse/cast cleanup listed above.

## Discovery notes
For each of the three form pairs, replace the two-function convention
with one function returning `{ ok: true; value } | { ok: false; errors }`;
the route's `if (!result.ok) return 422` then makes the good path
unreachable with bad input by construction, and `parseDollars`'s throw
becomes unreachable rather than merely guarded. Reconcile the listing
price grammar to one implementation shared by the error check and the
parse. For `isCheckoutComplete`, fold `missingCheckoutParts` into the
failure arm of a `parseCheckoutForm` that returns the union shape above,
so `CompleteCheckoutForm` is a distinct type from the raw one.

For `TransitionError`: `transitionListing` already has
`canTransitionListing` beside it — return
`{ ok: true; status } | { ok: false; reason }` from the transition and
let the action propagate it, so the route's `if (!result.ok)` is
exhaustiveness-checked instead of `instanceof`-checked. Keep
`TransitionError` only for the genuinely impossible —
`open-conversation.ts:35` throws `TypeError` for a malformed opening,
which is a programming error and should stay a throw.

Drop the two dead-weight casts and replace `default:` throws in
`decline-reason.ts` and `listing-stock.ts` with
`const _exhaustive: never = change` so the compiler enforces coverage;
drop the corresponding `as never` tests and the `?? []` widening once the
guard is gone. Make `asTallies` generic over the row
(`<Row extends { status: string; count: number }>` → `Tally<Row['status']>`)
or inline the three maps, so the cast in `platform-tallies.ts` goes.
Swap the `Readonly<Record<K, V>>` annotation for `satisfies` on the
lookup tables whose literal values matter (paths, transition targets,
column names). Either type the aggregate reads honestly
(`fn.sum<string | number | bigint>`) and funnel every read through one
`toCount(value): number`, or trust the `<number>` assertion and delete
the `Number()` calls — do not keep both. Collapse `AppConfig` with
`.transform()` on the schema and `type AppConfig = z.output<typeof environmentSchema>`.
Brand `Cents` as `number & { readonly __cents: unique symbol }` with
`parseDollars`/`centsFromColumn` as the only constructors, turning the
runtime `assertIntegerAmount` checks into compile errors where possible.
Branding ids and `Day` is out of scope for this ticket — `Day` is IMPRV-004's
concern.

Files this ticket is expected to touch: `app/core/shop/checkout-form.ts`,
`app/core/listings/listing-draft.ts`, `app/core/messaging/faq-draft.ts`,
`app/core/orders/shipment-details.ts`, `app/core/transition-error.ts`,
`app/core/listings/listing-status.ts`, `app/core/orders/order-status.ts`,
`app/core/orders/fulfillment-status.ts`, `app/core/payments/decline-reason.ts`,
`app/core/listings/listing-stock.ts`, `app/core/money.ts`,
`app/db/commerce-schema.ts`, `app/config.ts`,
`app/sites/admin/queries/platform-tallies.ts` and its sibling aggregate
query files, `app/sites/seller/routes/listings.ts`,
`app/sites/seller/routes/faqs.ts`, `app/sites/seller/routes/orders.ts`,
`app/sites/shop/routes/checkout.ts`, and the seven route-level
`TransitionError` catch sites.

This ticket, along with RFCTR-001 and RFCTR-002, must land before
IMPRV-002 (validation declared on routes) — IMPRV-002 touches the same
route handlers this ticket is pulling logic out of, and doing that pull
first keeps IMPRV-002's route-body changes small and stable.

## Related work
- 02-types-boundaries.md — "`isCheckoutComplete` validates but does not parse"
- 02-types-boundaries.md — "The same 'validate then separately parse' split in three more form paths"
- 02-types-boundaries.md — "Core throws `TransitionError` for expected business cases"
- 02-types-boundaries.md — "`errors: {} as ListingDraftErrors` — a cast with nothing to do"
- 02-types-boundaries.md — "`Object.fromEntries(...) as …` to recover key types"
- 02-types-boundaries.md — "Runtime guards for states the type system already excludes"
- 02-types-boundaries.md — "`satisfies` is never used; ~25 lookup tables are annotated and widened"
- 02-types-boundaries.md — "Aggregates are asserted `<number>` and then re-coerced with `Number()`"
- 02-types-boundaries.md — "`platform-tallies` widens a narrow row type and casts it back"
- 04-data-layer.md — "`platform-tallies.ts` widens a typed column back to `string`, then casts it"
- 02-types-boundaries.md — "`AppConfig` is hand-maintained beside the env schema"
- 02-types-boundaries.md — "Money and ids are bare `number`" (scoped here to `Cents` only)
- IMPRV-002 (validation on routes) depends on this ticket landing first
- BUG-003 (checkout can 500 or sell a removed listing) — the concrete
  `stockAfterSale`/`quantityWithinStock` `RangeError` violation of the
  `TransitionError` line documented here; distinct fix, same doctrine

## Working

**Re-validated.** Every problem in the ticket was still in the code, with two
exceptions.

- The `TransitionError` → result-union refactor is **not** in this ticket's
  change. The doctrine line it establishes is: `TransitionError` is for a
  user-triggerable, expected refusal; `RangeError`/`TypeError` are for
  programmer error only. That line is now written into the doc comment on
  `app/core/transition-error.ts`, where the class and its call sites can be
  judged against it. `transitionListing`/`transitionOrder`/`transitionFulfillment`
  still throw and the seven route `catch` blocks still catch — turning those
  into result unions touches the same route bodies IMPRV-002 owns and the same
  actions RFCTR-001/002 have just moved, so it is left for whoever lands next
  in those files.
- `platform-tallies.ts` no longer widens with `type CountedStatus`; the
  replacement `tallies<Status>` infers the union straight off the row, so
  `asTallies<OrderStatus>(listingRows)` is a compile error rather than a
  silent cast.

**One price grammar.** `isDollarAmount` and `parseDollars` share
`DOLLAR_AMOUNT_PATTERN` in `app/core/money.ts`, and `priceError` calls
`isDollarAmount`. The grammar chosen is `parseDollars`'s, the looser of the two:
`$249` and `1,234.00` are accepted (they were refused by `priceError` before and
accepted by `parseDollars`), `12.345` is refused by both. The form can no longer
hand `parseDollars` something it would throw on, which is what made the throw
reachable from a route.

**`Cents` is branded.** `number & { readonly __brand: 'Cents' }`, erasable
syntax. Three constructors, all in `app/core/money.ts`: `cents(n)` for literals
and fixtures, `parseDollars` for a form field, `centsFromColumn` for a money
aggregate. `cents` holds the file's only `as`, and every other money function
returns through it, so the integer check survives where the type cannot carry
it. `addCents`/`formatCents`/`dollarsInputValue` dropped their own
`assertIntegerAmount` calls — a `Cents` was checked when it was made.
`subtractCents` and `negateCents` are new because `a - b` and `-a` on a branded
number are plain `number`s; they replace the `addCents(x, -y)` and
`addCents(0, -y)` spellings. Money columns in `app/db/commerce-schema.ts` are
`ColumnType<Cents, number, number>`, so reads are `Cents` and writes still take
the plain integer the driver stores — that is what kept the seed and fixture
insert sites unchanged. `dollarsInputValue(cents(-105)) === '1.05'` is still
pinned by IMPRV-006's test.

**Aggregates.** `toCount(value: unknown): number` in `app/db/count.ts` is the
one reader; `countAll`/`sum` are now typed `string | number | bigint` (and
`| null` for a `sum` that can see no rows), which is what the driver actually
returns. `page-view-report.ts:73`'s `Number(counted.count ?? 0)` — the line that
contradicted its own `<number>` assertion — is `toCount(counted.count)`.
`find-storefront-listings.ts` and `current-cart.ts` are fixed too: both were
named in the ticket, and the first returned an asserted `<number>` straight out
of the function without so much as a `Number()`. The `Number(row.count)` reads
in `sites/seller/queries/*` and `sites/admin/queries/{seller,customer}-rows.ts`
are left alone — those queries never asserted `<number>`, so their `Number()` is
an honest conversion rather than half of a contradiction.

**Left alone deliberately.**
- `docs/ontology.md:221,343`, `docs/architecture.md:227`, `docs/messaging.md:75,101`
  and `docs/orders.md:35` name `faqDraftErrors`, `listingDraftErrors`,
  `isCheckoutComplete` and `type Cents = number`, none of which exist now.
  Docs are outside this ticket's territory this cycle — flagged for FEAT-017.
- `SIGNED_IN_ACTOR` and `ACTOR_GUARDS` in `plugins/identity.ts` keep their
  annotations: their values are functions, so `satisfies` would narrow nothing.
  `IDENTITY_COOKIES` (cookie names) got `as const satisfies`.
- The transition tables are `as const satisfies`, and `canTransitionListing`
  and friends widen to `readonly Status[]` in a local before `.includes` —
  a narrow tuple union has no callable `includes`. The `| undefined` annotation
  and the `?? []` on a total `Record` are gone, which is what the ticket asked
  for.

**Changed.** `app/core/money.ts` (+test), `app/db/commerce-schema.ts`,
`app/db/count.ts` (new, +test); the four form parsers
(`core/shop/checkout-form.ts`, `core/listings/listing-draft.ts`,
`core/messaging/faq-draft.ts`, `core/orders/shipment-details.ts`, each +test)
and their four route call sites (`sites/shop/routes/checkout.ts`,
`sites/seller/routes/{listings,faqs,orders}.ts`);
`sites/seller/listing-form.ts` (+test) now parses the multipart body with one
zod schema — the file part passes through by reference, because
`@fastify/multipart`'s `toBuffer` reads the part it is a method of and a copy
loses it; `app/config.ts`; the four admin aggregate queries plus
`shop/queries/find-storefront-listings.ts` and `actions/carts/current-cart.ts`;
`satisfies` on the lookup tables in `core/{listings/listing-status,orders/order-status,orders/fulfillment-status,auth/actor-type,messaging/conversation-kind,messaging/conversation-path,payments/decline-reason}.ts`,
`plugins/{identity,unread-messages}.ts`, `sites/auth/index.ts`,
`delivery/magic-link-delivery.ts`, `db/seed-page-views.ts`;
`core/listings/listing-stock.ts` and `core/payments/decline-reason.ts` lost
their unreachable guards (and the three `as never` tests that reached them);
`sites/shop/routes/carts.ts`; `core/transition-error.ts`; plus the callers and
fixtures the brand forced to compile.

**Casts.** The four non-`as const` casts the ticket named are gone:
`errors: {} as ListingDraftErrors` (twice), the three
`Object.fromEntries(...) as …`, the `Object.entries(...) as [PageViewSite, …][]`
in `seed-page-views.ts`, `row.status as Status` in `platform-tallies.ts`, and
`request.body as MultipartBody` in `seller/routes/listings.ts`. What remains in
`app/` outside tests is `amount as Cents` inside `cents()` (a brand has to be
constructed somewhere), and two that predate this ticket:
`JSON.parse(line) as Record<string, unknown>` in `test/log-lines.ts` and
`{ ...row } as R` in `db/node-sqlite-dialect.ts`.

**Tests.** Measured in an isolated worktree at HEAD, since the shared tree
carries IMPRV-003's in-flight work: 1461 pass before this ticket's change,
1470 after, 0 fail. `npm run check` exit 0 there, coverage 99.34 lines /
96.46 branches / 99.27 functions against thresholds of 95 and 90. In the shared
tree the same command reads exit 0, 1492 pass, 0 fail, 99.57/96.60/99.55.

Note for whoever holds `app.ts`: HEAD does not compile on its own —
`app/app.ts:13` imports `./logging.ts`, which IMPRV-003 has written but not yet
committed. The isolated verification above copied that one file in to get a
compilable baseline; nothing else of IMPRV-003's was needed.
