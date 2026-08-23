---
id: IMPRV-002
type: improvement
status: open
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
