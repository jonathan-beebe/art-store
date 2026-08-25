---
id: FEAT-008
type: feature
status: resolved
created: 
---

# FEAT-008: Final validation, review, and end-to-end smoke

## Problem
Feature tickets were built by separate agents in parallel; their integration, the README's accuracy, the coverage target, and the full loop have not been verified as one system.

## Goal
The prototype is demonstrably runnable and testable by a team member following only the README.

## Outcome
- From a clean checkout (remove `src/vendor`, the SQLite files, `app/assets/builds`), the README's first-run steps bring the app up and `make test` is green.
- `make coverage` reports ≥ 90% on `app/domain` and ≥ 80% overall; gaps are closed or listed in README "Known gaps".
- `make smoke` runs one integration test walking: seller sign-in → create listing → mark for sale → anonymous customer views, favorites, adds to cart → guest checkout → magic link → verify → pay 4242 → seller notification and ship → customer confirms delivery → payout run → earnings shows net = 90% of price.
- `docs/review.md` maps each requirement in `__local__/prompts/initial-prompt.md` to status, routes, and the test that proves it; lists known gaps and next steps.
- Conventions hold: no domain `if`s in controllers, sidecar tests beside every non-trivial file, no JavaScript required for any flow.

## Why it matters
This is the hand-off.

## Discovery notes
Run everything inside the container. `bin/rails routes` to cross-check docs. Small fixes get a test; large ones get a BUG ticket in `work/1-inbox/` and a line in `docs/review.md`.

## Working

### Clean first run

`docker compose down`, then `src/vendor`, `src/.bundle`, both SQLite files,
`src/app/assets/builds/*`, `src/tmp/*`, `src/log/*.log` and `src/coverage`
removed, then the README's first step alone: `make up`. The entrypoint
installed the bundle, `db:prepare` created **and seeded** the development
database, Tailwind built, and Puma answered in 40 seconds. `/`, `/seller`,
`/seller/login`, `/login`, `/cart`, `/favorites` and `/orders` all 200; the
`/assets/tailwind-<digest>.css` the HTML references serves 200 and 17,929
bytes. `make fresh` re-seeded under the running server and the storefront kept
answering 200 with the seeded listings — SQLite being dropped from a second
container does not disturb Puma. Nothing in the README or the entrypoint needed
correcting for the first run.

### Suite and coverage

- Before this ticket: 639 runs, 99.64% overall, Domain 99.84%. Five uncovered
  lines: `ActorType#customer?` (no caller anywhere) and four lines in
  `app/jobs/application_job.rb` and `app/mailers/application_mailer.rb`, which
  the suite never loads.
- After: **645 runs, 1604 assertions, 0 failures, 100.00% line coverage**, every
  group at 100%. The gap was closed by deleting dead code rather than by
  writing tests for methods nothing calls.

### Smoke

`src/test/smoke_test.rb`, one `ActionDispatch::IntegrationTest`, 105
assertions, 0.7s. `make smoke` runs it alone and `make test` includes it — the
`test` path was added to the `test` and `coverage` targets, matching what
FEAT-006 did for `db`.

Two `open_session` browsers rather than one: the seller keeps a portal session
while the customer arrives with no cookie at all, which is the only way the
walk proves the storefront hands a *fresh* visitor an anonymous row. Time is
frozen at Wednesday 2026-08-19 and the payout runs as-of the following Monday,
so the period the run settles is the week holding the delivery whatever day the
suite runs on. The magic link is read out of the rendered debug alert
(`css_select("[data-debug-alert] a")`), not out of the flash, so the page a
person would be looking at is what carries the walk forward.

### Curl walk over the running server

Storefront (anonymous cookie jar): `/`, all 12 `/art/:slug` pages on page 1,
`?q=`, `?medium=`, `?page=2`, `/cart`, `/favorites`, `/orders`, `/login` — all
200 with the stylesheet linked; `/art/no-such-listing` and `/orders/1` 404;
`/account` 302. Seller jar: `/seller` 302 while signed out, then sign-in
through the debug-alert link, `/seller`, `/seller/listings`, a listing, its
`/edit`, `/new`, `/seller/orders`, an order, `/seller/earnings`,
`/seller/notifications` — all 200. Another seller's listing, listing-status
POST, order, and shipment POST all 404. A live guest checkout ran cart →
`/checkout` → magic link → `/orders/4/pay` → declined `4000…0002` (order
`Payment failed`, decline notice rendered) → `4242…4242` → `Paid`. A listing
created through the portal with a multipart PNG stored the blob and
`/rails/active_storage/blobs/...` served it 200 as `image/png` — no libvips is
needed while nothing asks for a variant. `make fresh` afterwards.

No 500 and no broken layout anywhere in the walk.

### Conventions audit

- **One domain `if` in a controller.** `Seller::ShipmentsController` trimmed
  the carrier and tracking number and decided what made a shipment complete.
  Extracted to `Domain::Orders::ShipmentDetails` with a core sidecar; the
  controller now branches on `details.complete?` like every other form in the
  tree. Every other conditional in `app/controllers` reads a domain predicate
  or a shell fact (signed in, empty cart, missing row).
- **No `<script>` in any view**, no importmap, no `app/javascript`.
- **`bin/rails zeitwerk:check`**: all is good.
- **Sidecars**: 20 files under `app/` have none. All are at 100% line coverage
  through their callers' tests; `Shop::NotificationsController` is the only one
  with behavior of its own. Listed in `docs/review.md`.
- **Comments**: the only restatement in the tree was the generated one above
  `allow_browser versions: :modern`. Deleted.

### Deleted

`app/jobs/application_job.rb`, `app/mailers/application_mailer.rb`,
`app/views/layouts/mailer.{html,text}.erb`, the five empty `test/` scaffold
directories, and `Domain::Auth::ActorType#customer?` — none had a caller, and
the README already claimed there was no `test/integration`. The email hooks are
`MailMagicLinkDelivery` and `Notify#deliver_by_email`, neither of which touched
these files.

### Commits

| Commit    | What                                                                               |
| --------- | ---------------------------------------------------------------------------------- |
| `eee278f` | `test(smoke)` — the smoke test, `make smoke`, `test` in the test path              |
| `fec2a5c` | `fix(seller)` — `ShipmentDetails`; also carries the scaffolding deletions          |
| `e197e27` | `chore` — the dead predicate and the restating comment                             |
| `eeff93d` | `docs(review)` — `docs/review.md`                                                  |
| `367cafb` | `docs` — README smoke, card table, email hooks, known gaps; architecture test path |

`fec2a5c` carries the deletions that its message does not name: `git rm` had
already staged them when the shipment fix was committed. The tip's message was
amended to match its own contents rather than rewriting a branch another agent
was committing to.

### Gaps left open

In `docs/review.md` with next steps: email is a raising hook on both ports; the
payout button pays every seller; a merge can leave a customer with two carts
and the smaller one is unreachable; no order cancellation route; no libvips, so
no variants; seeded listings carry generated SVGs. None blocks the demo.
