---
id: FEAT-002
type: feature
status: open
created: 2026-08-22
---

# FEAT-002: Magic-link identity for sellers, customers, and admins with anonymous-customer merge

## Problem
After FEAT-001 the three sites render placeholders with no notion of who is visiting. The brief requires passwordless sign-in by magic link for sellers and customers (printed in a debug alert, with a hook for email later), anonymous customer ids assigned to every storefront visitor and merged into the account on verification, and a pre-seeded pair of admins who sign in the same way.

## Goal
Every request on every site resolves to an identity — anonymous customer, verified customer, seller, or admin — through one magic-link flow with one delivery port.

## Outcome
- A seller submits an email at `/seller/login`; the page shows a debug alert containing the link; following it signs the seller in (first link creates the seller) and lands on `/seller`. Same at `/login` for customers and `/admin/login` for admins.
- An email with no `admins` row cannot obtain an admin link; the two admins from the brief exist after `make fresh` and can sign in.
- A first storefront request sets a signed `customer_id` cookie for a new anonymous customer row; favorites and cart hang off it before any email is given.
- Verifying an email from an anonymous session claims the row, or folds it into the existing verified account: cart quantities sum (clamped to stock), favorites de-duplicate, every other owned row re-points, and a `customer_merges` row lets the old cookie resolve forward.
- Expired or consumed links are refused with a message and no identity change. `/account` exists on each site and shows the signed-in identity with sign-out.
- `MAGIC_LINK_DELIVERY=mail` selects the mail implementation, which throws `NotImplementedError` — the documented hook.
- Integration tests walk each flow through `app.inject`; the merge plan is a pure core function with its own test table.

## Why it matters
Guest checkout (FEAT-005), the admin site (FEAT-006), and messaging (FEAT-007) all assume this identity model; the retro named the merge-as-fold as a change both earlier spikes need.

## Discovery notes
Port from `prototype/rails/src/app/domain/auth/*`, `domain/customers/*`, `actions/auth/*`, `actions/customers/*`, `controllers/auth/*`, `controllers/concerns/customer_identity.rb`, and `docs/identity.md` — same decisions, TypeScript shape.
- Tables this ticket owns: `sellers`, `customers`, `admins`, `magic_links`, `customer_merges`. FEAT-003 runs in parallel and owns the commerce tables; `customer_merges` re-pointing of commerce rows is table-driven (a list of `{ table, column }` the fold walks, skipping tables that do not exist yet) so the two tickets do not block each other. Add each table's row type to `app/db/schema.ts` — touch only your own lines.
- Token: random 32 bytes, store a SHA-256 digest, compare by digest. Pure helpers for status (`usable` | `expired` | `consumed`) and for a safe local `redirectTo`.
- Identity plan (pure): given `{ anonymousCustomerId, ownerOfEmailId }` decide `createVerified` | `signInExisting` | `claimAnonymous` | `mergeAnonymousInto`; the merge plan (pure) takes both customers' cart lines and favorites and returns the folded cart lines and favorite set.
- The storefront resolves `currentCustomer` in a `preHandler` hook that creates the anonymous row when the cookie is absent; the auth routes never create one. `requireSeller` / `requireAdmin` / `requireVerifiedCustomer` as reusable hooks.
- Seed the two admins in `app/db/seed.ts` (FEAT-008 extends the seed; keep admins in a function it can call).
- Suggested TS interface: `interface MagicLinkDelivery { deliver(link: { email, url, actorType }, reply): void }` or return a value the route flashes — keep the port free of Fastify types if you can.

## Related work
- `prototype/rails/work/3-done/FEAT-002-magic-link-identity.md`
- `__local__/retro.md` items 3, 4, 7 (verify before card; merge is a fold; one messaging port).
