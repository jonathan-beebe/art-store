---
id: FEAT-016
type: feature
status: open
created: 2026-08-23
---

# FEAT-016: Structured JSON logs that tell the story

## Problem
The Rails prototype logs stock Rails prose (`request_id` as a tag in production only); there is no session id independent of sign-in, no actor on request lines, no unit-of-work id, and no domain events at all. `docs/alignment.md` §2 fixes the JSON payload, the `will`/`doing`/`did`/`refused`/`failed` phases, and the event vocabulary all three prototypes emit.

## Goal
Reading the log for one request or one `txn_id` tells what was about to happen, what happened, and why it stopped, in the payload every prototype shares.

## Outcome
Every log line is one JSON object on stdout with the §2.1 fields; `X-Request-Id` is echoed; a `sid` cookie is minted on the first response; every model/action that writes logs `will` then `did`/`refused`/`failed` under one `txn_id`; every §2.3 event the Rails prototype supports is emitted; cookie values, tokens, card numbers, and email addresses never appear; a test asserts the payload shape of a request + action and one test reads a captured log for the checkout story in order; `docs/architecture.md` gains a Logging section.

## Why it matters
The user is in rapid development: the log is the primary debugging surface, and a line without a session or actor cannot be joined to the lines around it.

## Discovery notes
Vanilla Rails: a custom `ActiveSupport::Logger` formatter emitting the JSON payload, `config.log_tags` replaced by a `CurrentAttributes` (`Current.request_id`, `Current.session_id`, `Current.actor`) that the formatter reads, `ActiveSupport::Notifications` for the request will/did lines, and a small `Story` module models call around `transaction do` that mints `txn_id`. Keep the default Rails request logging off (`config.rails_semantic_logger`-style silence is not needed; `Rails::Rack::Logger` can be muted).

## Related work
- docs/alignment.md §2

## Working

### What landed

- `src/lib/json_log_formatter.rb` — one JSON object per record on stdout, in
  every environment, built into `config.logger` in `config/application.rb`.
  The §2.1 fields are emitted in the contract's order; a record whose message
  is a String (framework prose, a gem's warning) is filed under
  `event: "app.log"`, `phase: "did"` rather than dropped, so nothing on stdout
  is un-parseable.
- `src/app/models/current.rb` — `ActiveSupport::CurrentAttributes` with
  `request_id`, `session_id`, `actor_type`, `actor_id`, `txn_id`, and
  `acting_as(record)`. `config.log_tags` and the tagged production logger are
  gone.
- `src/lib/story.rb` — `Story.tell(event, message, level:, **data) { |story| }`
  writes `will`, mints a `txn_id` when none is open, and writes the ending.
  `story.did` / `story.refused` record the ending; it is written when the
  story closes, so the line lands after the writing it describes. A
  `TransitionError`, `RecordInvalid`, or `RecordNotSaved` raised inside
  becomes `refused` at `info`; anything else `failed` at `error` with the
  `error` object. `Story.start` is the `will`-only form the request concern
  uses.
- `src/app/controllers/concerns/request_story.rb` — an `around_action` on
  `ApplicationController` resolving the three ids, naming the actor, and
  writing `http.request` `will`/`did`.
- `src/config/initializers/logging.rb` — every framework logger pointed at
  `File::NULL` (they all read their own, not `Rails.logger`), plus the
  `app.boot` / `app.shutdown` lines. `Rails::Rack::Logger` is deleted from the
  middleware stack in `config/application.rb`.
- `src/lib/tasks/logging.rake` — `migrate.run` / `migrate.apply` / `seed.run`
  around `db:migrate` and `db:seed`; `migrate.apply` comes from a module
  prepended to `ActiveRecord::Migration#migrate`, so it interleaves correctly.
- `src/test/support/log_capture.rb` — swaps `Rails.logger` for a StringIO
  logger with the same formatter and parses the lines back as JSON.
- `src/test/logging_test.rb` — eight tests: payload shape, the checkout story
  in order, the request-id echo, a malformed request id replaced, the `sid`
  cookie through sign-in and sign-out, a `refused` at info with the world
  unchanged, a `failed` at error with the `error` object, and no email address
  in any line.
- `docs/architecture.md` gains a Logging section: payload table, the pieces,
  a Mermaid sequence of one checkout, the vocabulary this prototype emits, and
  the deferred events.

### Events emitted

`http.request`, `magic_link.request`, `magic_link.consume`, `customer.merge`,
`listing.create`, `listing.update`, `listing.publish`, `listing.transition`,
`listing.view`, `cart.add`, `cart.update`, `cart.remove`, `order.place`,
`order.pay`, `fulfillment.ship`, `fulfillment.deliver`, `ledger.write`
(`debug`), `payout.run`, `payout.pay`, `conversation.open`, `message.post`,
`faq.publish`, `faq.unpublish`, `notification.write`, `notification.deliver`,
`migrate.run`, `migrate.apply`, `seed.run`, `app.boot`, `app.shutdown`.

### Deferred, deliberately

| Event | Ticket that brings it |
| --- | --- |
| `order.cancel`, `order.sweep` | FEAT-017 |
| `fulfillment.decline`, `refund.issue` | FEAT-017 |
| `moderation.remove_listing`, `moderation.lift_listing_removal`, `moderation.block_customer`, `moderation.lift_customer_block` | FEAT-021 |
| `rate_limit.exceed` | the rate-limit ticket (alignment §3) |
| `listing.view` `refused` at `debug` (the once-per-(listing, customer, hour) collapse) | FEAT-020 |

The features do not exist yet; the tickets that add them add their events.

### Decisions on ambiguities

- **`Story` does not open the transaction.** It mints the `txn_id` and is
  wrapped around the model's own `transaction do`, which keeps the writes
  where they were and lets the same helper serve rake tasks that must not run
  inside a transaction. Nested stories reuse the open `txn_id` and restore the
  outer one on the way out, so everything one action writes shares one id.
- **`request_id` shape.** §1's prefix table names no prefix for a request, so
  a minted id is `req_<ulid>` — the same generator, and a shape that satisfies
  the contract's `^[A-Za-z0-9_-]{1,64}$` so it round-trips through a proxy.
  The id is handed to `request.request_id`, because
  `ActionDispatch::RequestId` writes the response header after the action
  returns and would otherwise overwrite it.
- **`app.boot` / `app.shutdown` are single `did` lines**, not `will`/`did`
  pairs: a boot is one moment and a shutdown's second line would never be
  written. They are written straight to the logger rather than through
  `Story`, which also keeps application code out of the boot path (see the
  coverage note below).
- **`order.pay` `refused` writes a `payments` row**, so the world is not
  strictly unchanged. §2.3 names `order.pay` `refused` with `decline_reason`
  explicitly, and the vocabulary table wins over §2.2's general rule.
- **`cart.update`.** The storefront has no separate cart-edit action, so
  `Cart#add` tells `cart.add` for a new line and `cart.update` when the line
  was already there.
- **`listing.publish` vs `listing.transition`.** `Listing#transition_to!`
  tells `publish` when the move is to `for_sale` and `transition` otherwise;
  both carry `status_from`/`status_to`.
- **`ArgumentError` is a failure, not a refusal.** §2.2 names domain
  refusals; an `ArgumentError` here is a caller error (`a cart holds at least
  one of a listing`, `that listing is sold out` behind a `purchasable?`
  guard), so it is told as `failed` at `error`.
- **`order.place` `refused` carries `blocked_lines: []`.** The shape is wired;
  BUG-004 fills it.
- **Path redaction.** `http.request` logs `request.path` with any segment
  holding a parameter in `config.filter_parameters` replaced by `[FILTERED]`,
  which is what keeps the sign-in token out of the log. `:token` is already in
  that list.

### Deviations from the contract

- **A `event: "app.log"` fallback exists.** §2.1 says `event` is a dotted name
  from §2.3. A record the app did not write (a gem's warning, Minitest's own
  banner) still has to become one JSON object, so it is filed under `app.log`
  rather than dropped or written as prose. Nothing the app itself writes uses
  it.
- **`duration_ms` is written on `refused` as well as `did`/`failed`.** The
  contract marks it for `did`/`failed`; an extra key is allowed and the ending
  line is written the same way whatever the phase.

### Coverage note

`bin/rails test` boots the application (through its own `test:prepare`) before
it reaches `test/test_helper.rb`, so any file loaded during boot is already
required by the time SimpleCov starts counting. The logger is built at boot,
which pulls in `lib/json_log_formatter.rb` and — through the first line
anything writes — `app/models/current.rb`. Both are now `skip`ped in the
SimpleCov config with a comment saying why; they are still exercised by
`test/logging_test.rb`, the counter just cannot see them. Everything else
stays at 100%: **780 runs, 2608 assertions, 0 failures, 1496 / 1496 lines**
(from 772 / 2428 / 1317 before).

### Fix-up

A review of this commit found three defects, fixed here:

- **The coverage gate had a hole.** The two `skip` entries above were
  permanent and covered any future untested line in either file, not just
  the boot-order gap. Fixed by starting SimpleCov before the application
  boots instead of skipping the two files: `test/coverage_boot.rb` holds the
  `SimpleCov.start` block (plus the bundle setup a preload this early needs,
  since `config/boot.rb` hasn't put the bundle's gems on the load path yet)
  and the per-group `at_exit` printer. `Makefile`'s `test`, `smoke`, and
  `coverage` targets set `RUBYOPT='-r./test/coverage_boot'` on the
  `bin/rails test` command only — not on the `db:test:prepare` command that
  runs before it, so that process never opens its own empty coverage run.
  `test_helper.rb` requires the same file by relative path; `require` loads
  a given file once, so whichever entry point reaches it first — the
  `RUBYOPT` preload for a whole-suite run, or `test_helper.rb`'s own require
  for a path-filtered run, which skips `test:prepare` and boots the app from
  inside `test_helper.rb` after that require — is the one that runs, and
  the other's require is a no-op. Both files are now genuinely measured;
  line coverage rose to **1510 / 1510 (100%)**, no new tests needed since
  `test/logging_test.rb` already exercised every line. Verified both
  invocations directly: `bin/rails test` (whole suite) and
  `bin/rails test test/logging_test.rb` (path-filtered) both start coverage
  correctly with no double-start errors.
- **`fulfillment.decline` and `refund.issue` were attributed to FEAT-018.**
  Both belong to FEAT-017 (order lifecycle back half — cancel, sweep,
  decline, refund); FEAT-018 is rate limits and security headers and owns
  only `rate_limit.exceed`. Corrected in the deferred-events table above and
  in `docs/architecture.md`'s Logging section.
- **A failed migration left `migrate.run` with no ending line.** The
  `Rake::Task#enhance` block that wrote `did` and closed the story only ran
  after `db:migrate`'s own action succeeded, so a raised migration error
  skipped it entirely — no `failed` line, unlike every other story in the
  app. Fixed by wrapping the task's `execute` (which runs only the task's
  own actions, after the `telling_migrations`/`telling_seeds` prerequisite
  that opens the story) in a `rescue`/`ensure` that writes `failed` and
  re-raises on error, alongside the existing `did` on success. Same fix for
  `db:seed`/`seed.run`. Verified by raising inside a throwaway migration:
  `migrate.run` now writes `will` then `failed` with the error, and the
  original exception still propagates and aborts the task.
