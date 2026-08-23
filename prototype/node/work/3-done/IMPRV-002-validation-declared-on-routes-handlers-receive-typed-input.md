---
id: IMPRV-002
type: improvement
status: resolved
created: 2026-08-23
---

# IMPRV-002: Validation declared on routes, handlers receive typed input

## Problem
No route anywhere passes `schema: { body, querystring, params, response }`. `grep -rn "schema:" app/` over the tree returns only zod-internal parameter names, never a Fastify route option (`app/app.ts:45-77`; every file under `sites/*/routes/`). Validation happens inside handlers with ad-hoc zod calls (`form.parse(formBody(request))`, `parameters.safeParse(request.params)`), so the ajv serializer is never engaged and nothing declares what a route accepts.

`request.body` is cast to a hand-written type in the app's only multipart route: `const body = request.body as MultipartBody` at `app/sites/seller/routes/listings.ts:105` and `:139`, where `MultipartBody = Record<string, MultipartField | MultipartFilePart | undefined>` (`app/sites/seller/listing-form.ts:3-13`). Every other request body in the app goes through a zod schema; these two do not. The runtime is guarded at use sites instead — `textValue` re-checks `part.type === 'field' && typeof part.value === 'string'` and `uploadedImagePart` re-checks `part.type === 'file'` — so the cost is a type claim nobody earned plus checks scattered across call sites.

The same query-string id is parsed three different ways: `app/sites/admin/routes/payouts.ts:13,46-52` and `app/sites/admin/routes/ledger.ts:8,34-40` hand-roll an identical `parseSellerId`, while `app/sites/admin/routes/listings.ts:14`, `orders.ts:9`, `fulfillments.ts:9` use `z.coerce.number().int().positive().optional()`. The same split exists for text→positive-int ids generally: `app/plugins/id-param.ts` (zod), `app/plugins/identity.ts:102-109` (`ACTOR_ID` regex + `Number` + `>= 1`), and `app/actions/customers/resolve-customer-from-cookie.ts:34-41` (a verbatim copy of the latter under a different constant name).

`app/sites/admin/routes/ledger.ts` declares a loose schema and re-narrows it with two casts:
```ts
const filterQuery = z.object({ seller: z.string().optional(), type: z.string().optional() })
…
return (LEDGER_ENTRY_TYPES as readonly string[]).includes(value ?? '')
  ? (value as LedgerEntryType)
  : undefined
```
Two of the app's four non-`as const` casts live here, both avoidable — sibling routes (`admin/routes/orders.ts:8`, `fulfillments.ts:8`, `listings.ts:12-16`) already narrow inside the schema with `z.enum(...).catch(undefined)`.

`.catch()` defaults make bad input indistinguishable from absent input: `app/sites/shop/routes/carts.ts:23` — `z.object({ quantity: z.coerce.number().int().min(1).catch(1) }).catch({ quantity: 1 })` — means `POST /cart/:slug` with `quantity=-5` or `quantity=abc` silently adds one rather than answering 4xx.

Two ways of reading a form body coexist in one file: `app/sites/shop/routes/messages.ts:68` uses `replyBody.safeParse(request.body)` while `:105` uses `questionBody.parse(formBody(request))`; `app/sites/seller/routes/messages.ts:64` reads `request.body` directly. `formBody(request)` exists precisely because Fastify leaves `request.body` undefined for a bodyless POST.

## Goal
Every route declares its input shape as a schema, and handlers read already-typed values with no parse calls of their own.

## Outcome
- Every route declares its params/querystring/body as zod schemas through a validator compiler set once in `buildApp` (no type-provider dependency; a ~10-line type provider in `app/`).
- Handlers read typed `request.params`/`query`/`body` and contain no parse calls.
- `parseIdParam` and `formBody` are gone.
- A bad `:id` still answers 404, as the existing tests assert.
- The multipart body is parsed by one schema instead of a cast plus scattered guards.

## Why it matters
Fastify's doctrine calls for JSON schemas declared on routes for validation at the boundary; today validation is ad hoc, per-handler zod calls with no single point of enforcement. "Parse, don't validate at every boundary" is violated at the one route that casts its body instead of parsing it. The same id-parsing rule is duplicated at least three times, which is past the "abstraction earned at the third duplication" threshold the codebase otherwise follows. 07-showcase.md finding #11 identifies the same gap and proposes a validator-compiler approach scoped to one site's `params` schemas as a demonstrated pattern, citing it as an L-effort full sweep otherwise. This ticket takes the full sweep across all routes rather than the scoped version, using a small owned type provider rather than a third-party package, because the manifest calls for `parseIdParam`, `formBody`, and the duplicated id/query parsers to disappear entirely rather than to have one site as a partial example.

## Discovery notes
Set `app.setValidatorCompiler(({ schema }) => (data) => schema.safeParse(data))` in `buildApp` — no `fastify-type-provider-zod` package needed, the compiler hook is built into Fastify. A small (~10-line) type provider module in `app/` gives typed `request.params`/`query`/`body` without adding a dependency.

For the multipart route, give `listingDraftFieldsFrom`/`uploadedImagePart` an `unknown` parameter and one zod schema (`z.record` of a union, `z.custom` for the file part with `toBuffer`), parsed once at the top of `create`/`update`.

For the duplicated id parsers: one exported zod schema for a positive integer id, used by the params parser, the cookie readers, and the query filters.

For the ledger route casts: move both narrowings into the schema (`z.enum(LEDGER_ENTRY_TYPES).optional().catch(undefined)`), matching the sibling admin routes.

For the `.catch()` defaults hiding bad input: `.optional()` with an explicit `?? 1` distinguishes "not submitted" from "submitted garbage," so the latter can 422 like every other bad form.

For the split form-reading style: always use `formBody(request)`.

A bad `:id` answering 404 (not 400) is existing, asserted behavior (cross-actor ids answer 404 by design, per the curl walk in `docs/review.md`). The validator's own error path needs mapping back to that 404 rather than a generic 400 wherever the current tests assert 404.

Files expected to touch: `app/app.ts` (validator compiler registration), `app/plugins/id-param.ts` (shrinks or goes), `app/plugins/identity.ts`, `app/plugins/form-body.ts` (goes), `app/actions/customers/resolve-customer-from-cookie.ts`, `app/sites/seller/routes/listings.ts`, `app/sites/seller/listing-form.ts`, `app/sites/admin/routes/ledger.ts`, `app/sites/admin/routes/payouts.ts`, `app/sites/shop/routes/carts.ts`, `app/sites/shop/routes/messages.ts`, `app/sites/seller/routes/messages.ts`, and sidecar tests for every route touched.

Ordering: land after IMPRV-001 (routes need `setErrorHandler`/`setNotFoundHandler` in place before validator-compiler failures have somewhere to render). Land after RFCTR-001, RFCTR-002, and RFCTR-003. RFCTR-003 in particular changes how forms parse — `parseCheckoutForm` and its siblings return `{ ok, value | errors }` instead of validate-then-separately-parse — and this ticket's route-level schemas build on that shape rather than wrapping the old one.

## Related work
- 02-types-boundaries.md: "`request.body` cast to a hand-written type in the only multipart route", "The same query-string id is parsed three different ways", "`ledger.ts` declares a loose schema and re-narrows it with two casts", "`.catch()` defaults that make bad input indistinguishable from absent input", "Two ways of reading a form body in the same file"
- 03-core-shell.md: "Two query-parameter parsers duplicated, one of them a cast rather than a parse", "Cookie-id parsing duplicated"
- 05-shell-ops.md: "No route declares a schema"
- 07-showcase.md: showcase opportunity #11 ("zod as the Fastify validator compiler, schemas on `params`")
- IMPRV-001 (must land first), RFCTR-001, RFCTR-002, RFCTR-003 (must land first)

## Working

**Re-validated against the code first.** Three parts of the Problem had already
been fixed by the tickets that landed ahead of this one and are recorded here
rather than done again: `carts.ts` already used `.optional()` with an explicit
`?? 1` instead of `.catch(1)`; `ledger.ts` already narrowed `type` inside the
schema with `z.enum(...)` and held neither of the two casts the ticket quotes;
`resolve-customer-from-cookie.ts` no longer parses a cookie id at all, so the
only remaining duplicate of `parseActorId` was `sellerIdFromIdentityCookie` in
`seller/routes/listings.ts`, now replaced by `identityId(request, 'seller')`.
`parseIdParam`/`formBody` had also moved from `app/plugins/` to `app/http/`.

**The compiler and the type provider.** `app/http/zod-type-provider.ts` (new)
holds `ZodTypeProvider` — one `validator` member reading `z.output<schema>` —
plus `ZodRoutes`, the `FastifyPluginCallback` alias every route plugin is now
declared as, and `zodValidator`, the `FastifySchemaCompiler<z.ZodType>` that
`buildApp` hands to `app.setValidatorCompiler`. No package. `register` infers
the type provider per plugin, so the site plugins that hold no routes of their
own stay on the plain `FastifyPluginCallback` and only the ones that declare
routes carry `ZodRoutes` — no `withTypeProvider()` call was needed anywhere.

**The schema pieces.** `app/http/request-schema.ts` (new): `idValue`,
`idParams`, `slugParams`, `optionalFilter(schema)`, `submittedForm(fields)`.
`optionalFilter` is the empty-string fix — a `<select>`'s "all" option and an
emptied number input both submit `name=`, which `z.coerce.number()…optional()`
refused, so `/admin/payouts?seller=` was a 500 before this and is a 200 now
(the same held for `status=`, `type=`, `customer=`, `removed=` and the
storefront's `medium=`). It is a `z.preprocess` that maps `''` to undefined
ahead of `.optional()`. `submittedForm` is what replaces `formBody`: Fastify
hands a request that carried no body to the validator as `null` (not
`undefined`, so a bare `.default({})` would not fire), so the helper
preprocesses `null`/`undefined` into `{}` before the object schema.

**Params failures answer 404.** `app/plugins/error-pages.ts` gained
`isRefusedRouteParams(error)`, which reads the `validationContext` Fastify sets
on a wrapped validation failure; the root error handler answers a `'params'`
failure with `reply.callNotFound()`, so a bad `:id` renders the site's own
not-found page in the site's own layout. `'body'` and `'querystring'` fall
through to the existing 400 error page. Verified by hand across all three
sites, plus two new tests in `error-pages.test.ts` (a refused segment reaches
the not-found page; a refused query string reaches the 400 page).

**Behaviour that moved, deliberately.** Validation runs before `preHandler`, so
a signed-out visitor asking for a guarded url with an unparseable id now gets
404 instead of a redirect to the login page. Nothing asserted the old
behaviour, and a url that names nothing is not found whoever asks. Filter
values the schema does not recognise (`?status=nonsense`) are now 400 rather
than silently ignored on `/admin/customers`, matching what the three sibling
admin pages already did.

**Where a `.catch()` survives, and why.** `seller/routes/listings.ts`'s status
form keeps `z.enum(LISTING_STATUSES).optional().catch(undefined)`: an existing
test posts `status=on_loan` and asserts a 302 with the "Choose a status to
change to." flash, so a status the lifecycle does not name has to read as a
button that named nothing rather than as a 400. `shop/routes/messages.ts`'s
question form keeps `z.string().catch('')` for the same reason — an empty
question is refused by `postMessage`, which is what the test asserts.
`seller/listing-form.ts`'s multipart schema keeps its `.catch({})`/
`.catch(undefined)` per part, unchanged.

**The one parse call left in a routes file.** `renderOversizedImageForm`
(`seller/routes/listings.ts`) is the seller site's error handler for
`FST_REQ_FILE_TOO_LARGE`, which throws while the multipart body is still
parsing — before the route's own schemas run and before any `preHandler`. It
therefore reads the raw `request.params` through `idParams.safeParse` and the
seller through `identityId`, both explained in the comment above it. Every
route *handler* is free of parse calls.

**Business events left by IMPRV-003**, added while in these files:
`order.placed` and the `order.paid`/`order.declined` that follows it at
checkout (`shop/routes/checkout.ts` — `checkOutCart` now returns the charge it
settled alongside the placement, so the route can log both), and
`fulfillment.shipped` (`seller/routes/orders.ts`). `logChargeOutcome` moved out
of `order-payments.ts` into a new `shop/order-events.ts` alongside
`logOrderPlaced`, so checkout and the pay page log the same line the same way.
`listing.published`, the fourth event IMPRV-003 left behind, is not in this
ticket's scope and is still unplaced.

**Files changed.** New: `app/http/zod-type-provider.ts`,
`app/http/request-schema.ts` (+ sidecar tests), `app/sites/shop/order-events.ts`.
Deleted: `app/http/id-param.ts`, `app/http/form-body.ts` and both sidecars.
Changed: `app/app.ts`, `app/plugins/error-pages.ts`, all 13 admin route
modules that take input, all 6 seller route modules, all 10 shop route
modules, `app/sites/auth/index.ts`, `app/sites/auth/sign-in-routes.ts`,
`app/sites/seller/listing-form.ts` (the schema is exported, the
`parseListingFormBody` wrapper is gone), `app/sites/shop/customer-order.ts`
(`loadCustomerOrder` takes the parsed order id, `customerOrderPath` takes the
parsed params), `app/sites/shop/refuse-blocked-customer.ts` (the guard is typed
by its route's params so its destination reads them without a parse), plus
`docs/architecture.md` (Coordination row and the source tree) and `docs/admin.md`
(the `moderationRoute` paragraph named `formBody`).

**Left alone.** The three admin pages that take no input at all
(`home.ts`, `stats.ts`, `accounting.ts`), `/health`, `/events`, `/logout` and
`/account` declare no schema, because they read nothing off the request. The
outbox show route keeps its plain-text 404 for a message id that names nothing
— that is its existing answer and no part of this ticket asks it to change.

**Tests.** 1519 → 1534 pass, 0 fail (the count drops 9 for the two deleted
sidecars and rises 24 for the new ones). New: `app/http/request-schema.test.ts`
(9), `app/http/zod-type-provider.test.ts` (2), two in `error-pages.test.ts`,
one empty-filter route test on each of the six admin filter pages, two checkout
event tests, one `fulfillment.shipped` test, one bodiless-reply test on the
storefront message thread. `npm run check` exit 0 at 99.57 lines / 97.22
branches / 99.47 funcs; `npm run routes` prints the same table it did before.
