---
id: BUG-001
type: bug
status: resolved
created: 2026-08-23
---

# BUG-001: Admin lift routes answer 500 when the request has no body

## Problem
`POST /admin/customers/:id/blocks/lift` and `POST /admin/listings/:id/removals/lift` return 500 for a request with no body. `moderationRoute` in `src/app/sites/admin/routes/moderation.ts` calls `command.form.parse(request.body)` and Fastify leaves `request.body` undefined when no body was sent, so zod throws instead of reporting a validation result. Found during the FEAT-007 curl walk with `curl -X POST`.

## Goal
Every admin write route answers a well-formed 4xx or a redirect for any request shape, never a 500.

## Outcome
A bodiless POST to either lift route (and to the remove / block routes) is handled the same way as a POST with missing fields; an integration test beside the route proves it.

## Why it matters
A 500 on an admin action reads as a crash in a demo, and the same pattern may exist in other routes that parse `request.body` directly.

## Discovery notes
`request.body ?? {}` before parsing is the smallest fix; check the other sites' routes for the same call shape while there.

## Related work
- FEAT-006 (admin site), FEAT-007 (found it)

## Working

### Reproduced

Against the running server on `http://localhost:4000`, signed in as
`jonathan-beebe@outlook.com`:

| Request                                                   | Before | After |
| --------------------------------------------------------- | ------ | ----- |
| `POST /admin/listings/1/removals/lift` (no body)          | 500    | 302   |
| `POST /admin/customers/1/blocks/lift` (no body)           | 500    | 302   |
| `POST /admin/listings/1/removals` (no body)               | 500    | 400   |
| `POST /admin/customers/1/blocks` (no body)                | 500    | 400   |
| `POST /admin/listings/1/removals` with only `redirect_to` | 500    | 400   |
| `POST /admin/customers/1/blocks` with only `redirect_to`  | 500    | 400   |

The last two rows widen the ticket: a POST with *missing fields* answered 500
too, so `request.body ?? {}` on its own would not have reached the goal for the
remove and block routes. Their forms require `kind` / `reason`, so an empty
object still fails the schema.

### The fix

`formBody(request)` in `app/plugins/form-body.ts` returns `request.body ?? {}`.
`app/plugins/` already holds request-level helpers that are not `addX(app)`
plugins (`signedInActorId`, `identityCookieValue` in `identity.ts`), so a
one-function module fits there.

Applied at every route that fed a body straight into a throwing `zod.parse`:

| File                                   | Call site                   |
| -------------------------------------- | --------------------------- |
| `app/sites/seller/routes/faqs.ts`      | `publish`, `update`         |
| `app/sites/seller/routes/orders.ts`    | `ship`                      |
| `app/sites/auth/sign-in-routes.ts`     | `POST /login`               |
| `app/sites/shop/routes/messages.ts`    | `POST /art/:slug/questions` |
| `app/sites/shop/routes/checkout.ts`    | `POST /checkout`            |
| `app/sites/shop/routes/carts.ts`       | `POST /cart/:slug`          |
| `app/sites/admin/routes/payouts.ts`    | `POST /admin/payouts`       |
| `app/sites/admin/routes/moderation.ts` | all four writes             |

Every one of those schemas already treats each field as optional or carries a
zod `.catch`, so an empty object drops into the route's own "you did not fill
this in" path: the FAQ publish flashes `Enter the question.`, the ship route
flashes that a shipment needs a carrier and a tracking number, `/login` flashes
`Enter an email address to sign in.`, checkout re-renders at 422, the question
route redirects back to the listing, and the payout run falls back to today.
`addForm` in `carts.ts` wraps its whole object in `.catch({ quantity: 1 })`, so
that route already answered; its test locks the behavior in.

`moderationRoute` needed more than the helper. It now `safeParse`s and answers
`badRequest(reply)` — 400, `text/plain`, the same shape as the `notFound(reply)`
beside it — when the form does not hold together. The two lift routes take only
an optional `redirect_to`, so they redirect for a bodiless POST; remove and
block answer 400.

### Left alone

The `safeParse(request.body)` call sites already report a clean failure for an
undefined body and their routes already handle it:
`app/sites/seller/routes/messages.ts`, `app/sites/seller/routes/listings.ts`,
`app/sites/shop/routes/messages.ts` (the reply), `app/sites/shop/routes/order-payments.ts`,
`app/sites/admin/routes/messages.ts`. Wrapping them would change what the schema
sees without changing what the visitor gets.

`request.query` and `request.params` are always objects in Fastify, so the
`parse(request.query)` and `parse(request.params)` sites are not this shape.

### Verified

- 14 new tests across 9 sidecar files (2 in `app/plugins/form-body.test.ts`,
  5 in `app/sites/admin/routes/moderation.test.ts`, 1 each in the seller FAQ,
  seller orders, sign-in, shop messages, shop checkout, shop carts, and admin
  payouts test files).
- `docker compose run --rm app npm run check`: **1170 tests, 1170 pass, 0 fail**,
  typecheck and eslint clean.
