---
id: BUG-004
type: bug
status: open
created: 2026-08-23
---

# BUG-004: Magic-link sign-in and identity claims run outside any transaction

## Problem
`app/actions/customers/merge-anonymous-customer.ts:40` calls
`await db.transaction().execute(async (trx) => {` directly — the only place
in the app that calls `db.transaction()` instead of `runInTransaction`
(guard at `app/actions/transaction.ts:13`). `transaction.ts` exists precisely
because SQLite refuses a nested `BEGIN`, so an action that calls another
action has to pass its own handle down. This action is called from
`claimCustomerIdentity` (`actions/customers/claim-customer-identity.ts:41`),
which is called from `signInWithMagicLink`
(`actions/auth/sign-in-with-magic-link.ts:102`). It works today only because
neither caller opens a transaction; the first caller that does gets
`SqliteError: cannot start a transaction within a transaction`.

`app/actions/auth/sign-in-with-magic-link.ts:40-62` runs the whole sign-in
with no `BEGIN`: read `magicLinks` (40-44) → compute status in core (49) →
guarded update to consume (58, via `consume` at 81-90) → `claimActor` (62),
which creates or updates a seller/customer. Four statements, no transaction.
The consume itself is safe (`where consumedAt is null` +
`numUpdatedRows > 0n` is a proper compare-and-swap), but the link is spent
before the actor exists: if `claimSellerIdentity` or `claimCustomerIdentity`
throws, the link is burned and the visitor cannot retry.

`app/actions/auth/claim-seller-identity.ts:24-52` selects by email (24-28),
branches (30), then either updates (33-37) or inserts (42-52).
`app/actions/customers/claim-customer-identity.ts:24-47` reads the owner of
the address (24) and the anonymous row (27), hands both to
`planCustomerIdentity` (26-29), then applies one of four plans, one of which
is a write followed by a whole merge (39-47). Neither opens a transaction.
Two links for the same address arriving together both see no row and both
insert, hitting the `sellers_email` / `customers_email` unique constraint — a
500 rather than a sign-in. `claimSellerIdentity:39` also returns
`{ ...existing, emailVerifiedAt: verifiedAt }`, a row reconstructed in JS
rather than read back, so it can report a state the database never held.

Nine actions declare bespoke `{ db: AppDatabase; clock: Clock }` parameter
objects instead of `ActionContext`:
`app/actions/auth/claim-seller-identity.ts:8-11`,
`app/actions/auth/send-magic-link.ts:14-20`,
`app/actions/auth/sign-in-with-magic-link.ts:26-29`,
`app/actions/auth/find-admin-by-email.ts:11`,
`app/actions/customers/claim-customer-identity.ts:19`,
`app/actions/customers/create-anonymous-customer.ts:11-17`,
`app/actions/customers/merge-anonymous-customer.ts:37`,
`app/actions/customers/resolve-current-customer.ts:15`,
`app/actions/customers/resolve-customer-from-cookie.ts:13`. This is the
structural reason none of them can join a transaction: `runInTransaction`
takes an `ActionContext`, so a type that is not one cannot be threaded
through it.

Separately, cookie-id parsing is duplicated: `app/plugins/identity.ts:22,102-109`
and `app/actions/customers/resolve-customer-from-cookie.ts:34-42` both hold
`/^[0-9]+$/` and `return id >= 1 ? id : null`.

## Goal
Consuming a magic link and claiming the signed-in actor happen as one atomic operation, and every action under `app/actions` shares one context shape.

## Outcome
- Consuming a link and claiming the actor commit or roll back together.
- Two links for one address arriving together produce one row and two sign-ins, not a 500.
- Every action under `app/actions` takes `ActionContext`.

## Why it matters
"Any read-then-write runs inside a single transaction" — this is the module's
own stated contract (`docs/architecture.md`, `docs/review.md`) and this action
family contradicts it. "DB rows crossing back are parsed once in the shell" —
`claimSellerIdentity`'s reconstructed return value violates that by reporting
a state never read back from the database. "Explicitness over cleverness" —
two parallel action-parameter conventions in one directory is implicit
structure that determines, silently, which actions can nest.

## Discovery notes
Change `mergeAnonymousCustomer`'s signature to take `ActionContext` and wrap
with `runInTransaction`, same as every other action; the body already takes a
`trx` handle it threads to every helper, so the change is the wrapper and the
parameter type.

Take `ActionContext` in `signInWithMagicLink` and wrap the whole body in
`runInTransaction` so consuming the link and claiming the actor commit or
roll back together.

Give `claimSellerIdentity` and `claimCustomerIdentity` `ActionContext` and
`runInTransaction`. Once inside one transaction the plan/apply split in
`claimCustomerIdentity` is sound; return the row from a `returningAll()` or a
read-back instead of reconstructing it in `claimSellerIdentity`.

Move all nine listed actions to `ActionContext` (or
`Pick<ActionContext, 'db'>` for the pure readers) — this is the enabling
change the three transaction fixes above depend on.

Export one `parseActorId(value: string | null | undefined): number | null`
from `app/plugins/identity.ts` and have `resolve-customer-from-cookie.ts` take
an already-parsed `number | null` rather than re-deriving it.

Files expected to touch: `app/actions/customers/merge-anonymous-customer.ts`,
`app/actions/auth/sign-in-with-magic-link.ts`,
`app/actions/auth/claim-seller-identity.ts`,
`app/actions/customers/claim-customer-identity.ts`,
`app/actions/auth/send-magic-link.ts`, `app/actions/auth/find-admin-by-email.ts`,
`app/actions/customers/create-anonymous-customer.ts`,
`app/actions/customers/resolve-current-customer.ts`,
`app/actions/customers/resolve-customer-from-cookie.ts`,
`app/plugins/identity.ts`.

This ticket should land before BUG-005: both touch the same identity/action
family and BUG-005's transaction wrapping is cleaner once every action here
takes `ActionContext`.

## Related work
- 04-data-layer.md — "`mergeAnonymousCustomer` opens its own transaction, bypassing the nesting guard"
- 04-data-layer.md — "The whole magic-link sign-in runs with no transaction"
- 04-data-layer.md — "`claimSellerIdentity` and `claimCustomerIdentity` read-then-write outside a transaction"
- 04-data-layer.md — "The auth/customers action family does not take `ActionContext`"
- 03-core-shell.md — "Cookie-id parsing duplicated"
- BUG-005 (land after this ticket)
