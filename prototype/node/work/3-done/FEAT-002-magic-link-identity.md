---
id: FEAT-002
type: feature
status: resolved
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

## Working

### What exists

Core (pure, sidecar tested, no database):

| Module                                            | Exports                                                                                        |
| ------------------------------------------------- | ---------------------------------------------------------------------------------------------- |
| `app/core/auth/actor-type.ts`                     | `ACTOR_TYPES`, `ActorType` (`seller` \| `customer` \| `admin`), `isActorType`, `ACTOR_SITES`   |
|                                                   | (`homePath`, `loginPath`, `signedOutPath` per actor)                                           |
| `app/core/auth/email-address.ts`                  | `normalizeEmail`, `isEmailAddress`                                                             |
| `app/core/auth/magic-link-token.ts`               | `digestMagicLinkToken` (sha256 hex)                                                            |
| `app/core/auth/magic-link-status.ts`              | `MAGIC_LINK_LIFETIME_MINUTES` (15), `magicLinkStatus(link, now)`,                              |
|                                                   | `magicLinkExpiresAt(issuedAt)`                                                                 |
| `app/core/auth/local-redirect.ts`                 | `keepLocalRedirect(requested, origin)`,                                                        |
|                                                   | `resolveLocalRedirect(requested, { fallback, origin })`                                        |
| `app/core/customers/identity-plan.ts`             | `planCustomerIdentity({ anonymousCustomerId, ownerOfEmailId })`, `resultingCustomerId(plan)`   |
| `app/core/customers/customer-merge-plan.ts`       | `planCustomerMerge({...})` → `{ cartLines, favoriteListingIds }`, `CartLine`                   |
| `app/core/customers/repointed-customer-tables.ts` | `REPOINTED_CUSTOMER_TABLES`                                                                    |
| `app/core/customers/customer-verification.ts`     | `isVerifiedCustomer`, `isAnonymousCustomer`                                                    |

Actions (`{ db, clock }`, integration tested against `:memory:`):

- `sendMagicLink({ db, clock, delivery, magicLinkUrl }, { email, actorType, redirectTo? }): Promise<Flash>`
- `signInWithMagicLink({ db, clock }, { token, currentCustomerId }): Promise<MagicLinkSignIn>` —
  `{ outcome: 'unknown' } | { outcome: 'refused', actorType, refusal: 'expired' | 'consumed' | 'unrecognized' } | { outcome: 'signedIn', actorType, actorId, redirectTo }`
- `claimSellerIdentity({ db, clock }, email)`, `findAdminByEmail({ db }, email)`
- `claimCustomerIdentity({ db, clock }, { email, currentCustomerId })`
- `mergeAnonymousCustomer({ db, clock }, { anonymousCustomerId, verifiedCustomerId })`
- `resolveCustomerFromCookie({ db }, cookieValue)`, `resolveCurrentCustomer({ db, clock }, cookieValue)`,
  `createAnonymousCustomer({ db, clock })`

Delivery port: `app/delivery/magic-link-delivery.ts` — `MagicLinkDelivery` is
`{ deliver(message: { email, url, actorType }): Flash }`, with
`flashMagicLinkDelivery` and `mailMagicLinkDelivery` (throws
`NotImplementedError`), chosen by `selectMagicLinkDelivery(config.magicLinkDelivery)`
from `MAGIC_LINK_DELIVERY` (`flash` | `mail`, default `flash`; anything else is
refused by zod in `loadConfig`).

Routes: `app/sites/auth/index.ts` owns `GET /auth/magic/:token`.
`signInRoutes({ actorType, admits?, refusal? })` registers `GET/POST /login`,
`POST /logout`, and `GET /account` inside whichever site plugin registers it,
so all three sites share one implementation and keep their own layout and
templates. The admin site passes `admits` so an address with no `admins` row
never gets a link.

### The hooks and helpers the next tickets call

From `app/plugins/identity.ts`:

| Symbol                                                     | What it does                                                                          |
| ---------------------------------------------------------- | ------------------------------------------------------------------------------------- |
| `addIdentity(app)`                                         | Called once in `buildApp`. Decorates `request.currentSeller` / `currentCustomer` /    |
|                                                            | `currentAdmin` / `identity`, `reply.signIn(actorType, id)`,                           |
|                                                            | `reply.signOut(actorType)`, and adds the root `preHandler` that resolves the seller   |
|                                                            | and admin from their cookies.                                                         |
| `resolveCustomerIdentity`                                  | `preHandler`. Puts a customer on the request, **creating an anonymous row when the    |
|                                                            | cookie names nobody**, and rewrites the cookie so a merged id rolls forward.          |
|                                                            | Registered in `app/sites/shop/storefront.ts`; every storefront page FEAT-005 adds     |
|                                                            | goes inside that plugin and inherits it.                                              |
| `rememberCustomerIdentity`                                 | `preHandler`. Same, minus the creation. Used by the storefront's sign-in routes.      |
| `requireSeller`, `requireAdmin`, `requireVerifiedCustomer` | `preHandler` guards. Flash an alert and redirect to that side's `loginPath` with      |
|                                                            | `?redirect_to=<request.url>`.                                                         |
| `ACTOR_GUARDS`                                             | The three guards keyed by `ActorType`.                                                |
| `signedInActorId(request, actorType)`                      | The id this request is signed in as, or null. A customer counts only once `email` is  |
|                                                            | set.                                                                                  |
| `identityCookieValue(request, actorType)`                  | The raw unsigned cookie value, for code that must not create a row.                   |

`currentCustomer` is the whole customer row, so `request.currentCustomer.id` is
the owner for carts, favorites, orders, and listing events.

Cookies are `seller_id`, `customer_id`, `admin_id` — signed, httpOnly,
`sameSite=lax`, one year. They are independent, so one browser is all three at
once (`app/plugins/identity.test.ts` proves it).

Test helpers in `app/test/build-test-app.ts`:

- `signInAsSeller(testApp, email?)`, `signInAsCustomer(testApp, email?)`,
  `signInAsAdmin(testApp, email?)`, `browseAsAnonymousCustomer(testApp)` — each
  returns `{ id, cookies }`; spread `cookies` into `app.inject({ cookies })`.
  They go through the actions, not HTTP, so a test about something else does
  not walk the link flow.
- `takeDebugMagicLink(testApp, response)` — the sign-in URL a response flashed,
  for a test that does want to walk the flow.

### The merge fold

`REPOINTED_CUSTOMER_TABLES` is data — `{ table, column }` in snake_case,
because it is matched against `sqlite_master` at run time:
`orders`, `listing_events`, `notifications`, `conversations`.

Carts and favorites are **deliberately absent** from that list: re-pointing
`carts.customer_id` is the bug retro item 4 names, so they are folded instead.
`app/actions/customers/merged-table-columns.ts` reads `sqlite_master` plus
`pragma_table_info` for the eight tables a merge can touch, and every step
checks its columns before running. A table that does not exist is skipped
(`it skips a table the schema does not have yet and still writes its trail`),
and a table that arrives later is picked up with no change here (`a table that
arrives later is re-pointed without any change here`, which creates
`conversations` mid-test).

The cart fold applies through UPDATE and DELETE only — never an INSERT — so it
needs no knowledge of the columns it is not touching (`created_at`, and
`cart_items` has none). When the account has no cart, the anonymous cart row is
re-pointed whole; when both have one, the folded lines are written into the
account's cart and the anonymous cart goes away. Favorites are the same shape:
duplicates are deleted, the rest re-pointed.

Columns the fold reads, confirmed against FEAT-003's migrations as they landed:
`carts.customer_id`, `cart_items(cart_id, listing_id, quantity)`,
`favorites(customer_id, listing_id)`, `listings(id, quantity)`.

### Decisions

- **Timestamps are ISO-8601 UTC text** (`app/db/timestamp.ts`: `Timestamp`,
  `toTimestamp`, `fromTimestamp`, `fromNullableTimestamp`). SQLite orders that
  format lexicographically, so an expiry check is a plain `<` and needs no date
  functions. Only `created_at` is stored; there is no `updated_at` anywhere —
  the times that mean something have names (`email_verified_at`,
  `consumed_at`).
- **The identity plan is a discriminated union**, not a record with four
  nullable ids. Each action carries exactly the ids it needs, so the shell
  reads them with no null assertions.
- **One use is enforced by the UPDATE, not by the read**:
  `set consumed_at = now where id = ? and consumed_at is null`, and the sign-in
  is refused when it updates no row. Two requests arriving together cannot both
  spend a link.
- **A refusal names the actor type**, so `/auth/magic/:token` sends the visitor
  back to the right sign-in page without knowing which site issued the link. An
  unknown token has no actor type and falls back to the storefront's.
- **An admin row that disappears refuses with `unrecognized`.** Admin rows are
  seeded, so this is the only way a spent link can name nobody.
- **`signInRoutes` is a plugin factory rather than three copies.** The customer
  variant adds `rememberCustomerIdentity` to itself, which is how asking for a
  link avoids minting an anonymous row: the creating hook lives in the sibling
  `storefrontRoutes` plugin, and Fastify's encapsulation keeps it there.
- **`addSiteRender` hands every template `identity`.** A layout has to show who
  is signed in on every page, including pages that do not require anyone, so
  the render decorator supplies it rather than each route remembering to.
- **A bad address redirects with a flash instead of rendering 422.** The flash
  is a one-request cookie with no `flash.now`, so a page cannot show a message
  set during its own request. Post-redirect-get keeps one mechanism. (The Rails
  spike rendered 422 here.)
- **`app/db/seed-admins.ts` holds `seedAdmins({ db, clock })` and
  `app/db/seed.ts` is the entry that calls it** — the same split as
  `migrator.ts` / `migrate.ts`. FEAT-008 adds its seed functions beside it and
  calls them from `seed.ts`. The ticket said to put the function in `seed.ts`;
  splitting it keeps `seed.ts` importable-free of top-level await side effects.
- **`NotImplementedError` lives at `app/not-implemented-error.ts`**, beside
  `clock.ts`, because it is not specific to delivery.

### Deviations

- `app/db/schema.ts` now names its tables instead of being
  `Record<never, never>`; the five identity row types are there. FEAT-003 put
  its tables in `app/db/commerce-schema.ts`.
- The Rails spike's `MergeAnonymousCustomer` re-points `carts` and `favorites`.
  This one folds them, per retro item 4, which is why the two table lists
  differ.
- Rails has no admin side at all; the third actor type, `ACTOR_SITES.admin`,
  the `admits` hook, and the seeded-only rule are new here.
- `docker/entrypoint.sh` seeds the admins after migrating, so a clean clone's
  `make up` can reach `/admin`. The entrypoint is baked into the image, so that
  line takes effect on the next `docker compose build` — an already-running
  container needs `make seed`. `make fresh` seeds without a rebuild.

### Verified

- `make test` (`npm run check`: typecheck, eslint, `node --test`): **593 tests,
  593 pass, 0 fail** across both tickets sharing the tree. **188 of those are
  this ticket's**, in 28 sidecar files.
- `make coverage`: **99.27% lines, 97.32% branches, 98.00% functions**, exit 0.
  Thresholds are 90 lines / 80 branches.
- eslint clean over every path this ticket owns (`complexity` 8, `max-depth` 3).
  `signedInActorId` hit complexity 9 as an if-chain and became a lookup table.
- Curl walk against the running server on `http://localhost:4000`, all three
  sites: `GET /login` 200 → `POST /login` 302 → the debug alert prints
  `http://localhost:4000/auth/magic/<64 hex>` → following it redirects to
  `/seller`, `/account`, `/admin` respectively and sets that site's cookie →
  the account page shows the address and a sign-out form.
- Spending a link twice from a fresh browser renders "That sign-in link has
  already been used. Ask for a new one."; an unissued token renders "That
  sign-in link is not valid. Ask for a new one."
- `POST /admin/login` with `stranger@example.com` issues no link and renders
  "That address cannot sign in to the admin site."; the two seeded admins sign
  in.
- `MAGIC_LINK_DELIVERY=mail` selects the mail implementation, which raises
  `NotImplementedError: Email delivery is not implemented yet`.
- `make seed` prints `seeded 2 admins`; running it again adds nobody.
