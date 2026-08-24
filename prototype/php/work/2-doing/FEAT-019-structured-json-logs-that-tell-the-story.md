---
id: FEAT-019
type: feature
status: open
created: 2026-08-23
---

# FEAT-019: Structured JSON logs that tell the story

## Problem
The PHP prototype makes zero `Log::` calls; the stock Laravel `single` channel writes prose lines with no request id, no session id, no actor, and no unit-of-work id. `docs/alignment.md` §2 fixes the JSON payload, the `will`/`doing`/`did`/`refused`/`failed` phases, and the event vocabulary all three prototypes emit.

## Goal
Reading the log for one request or one `txn_id` tells what was about to happen, what happened, and why it stopped, in the payload every prototype shares.

## Outcome
Every log line is one JSON object on stdout with the §2.1 fields; `X-Request-Id` is echoed; a `sid` cookie is minted on the first response; every domain action logs `will` then `did`/`refused`/`failed` under one `txn_id`; every §2.3 event the PHP prototype supports is emitted; cookie values, tokens, card numbers, and email addresses never appear; a test asserts the payload shape of a request + action and one test reads a captured log for the checkout story in order; `docs/architecture.md` gains a Logging section.

## Why it matters
The user is in rapid development: the log is the primary debugging surface, and a line without a session or actor cannot be joined to the lines around it.

## Discovery notes
Idiomatic Laravel: a `stdout`/`stderr` channel with a custom Monolog formatter for the payload, `Log::withContext()` from one middleware for `request_id`/`session_id`/`actor_*`, and a small `Story` (or `Journal`) helper that domain actions call as `will()`/`did()`/`refused()`/`failed()` and that mints `txn_id` around `DB::transaction`. The existing `MessagePosted`-style events can carry the domain lines.

## Related work
- docs/alignment.md §2
- RFCTR-007 (events)

## Working

### What landed

- `App\Logging\StoryFormatter` — the Monolog formatter behind the one channel
  that writes lines. It spells §2.1 field for field, in the table's order,
  leaving out every field the record carries nothing for. `ts` is ISO-8601 UTC
  with milliseconds; `warning` is spelled `warn`; an exception in the record's
  `exception` key becomes `{type, message}` plus `stack` while `APP_DEBUG` is
  on. A line the framework wrote on its own still comes out as a payload:
  `app.log` names the source, and an error-level line reads as `failed` while
  anything quieter reads as `doing`.
- `App\Logging\StoryEvent`, `StoryPhase`, `StoryLevel` — the vocabulary as
  enums. `StoryEvent` carries the three rules the contract states about
  particular events: `ledger.write` is `debug`, a `listing.view` refusal is
  `debug`, and `http.request` is not a unit of work.
- `App\Support\Story` — `will` / `doing` / `did` / `refused` / `failed`.
  `will` mints the `txn_id` and pushes it on a stack; the ending pops it, so a
  line written inside a unit of work — the action's own, the ledger entries and
  notifications that fall out of it — carries the same value, and a unit
  opened inside another names the innermost. `did` and `failed` carry
  `duration_ms` when a `will` preceded them. `follows()` and `actorIs()` put
  the request marks in the logger's context.
- `App\Support\IdMint` — mints `req_`, `ses_`, and `txn_` through the same
  `PrefixedId` value object every row's key comes from.
  `HasPrefixedUlid::newUniqueId()` now goes through it too, so there is one
  spelling of the format.
- `App\Http\Middleware\LogRequestStory` — appended to the `web` group.
  Mints or honours `request_id` and echoes it as `X-Request-Id`; reads or mints
  the `sid` cookie (`ses_<ulid>`, a year, untouched by sign-in and sign-out);
  names the actor from the seller, admin, or customer guard, falling back to
  the identity cookie; writes `http.request` `will` on entry and `did` on
  response with `status` and `duration_ms`, or `failed` with the exception
  before letting it through.
- `App\Providers\LoggingServiceProvider` — the lines nothing calls for:
  `app.boot`, `app.shutdown` (a terminating callback — PHP's server runs one
  request per process), `migrate.run` / `migrate.apply` off the framework's
  migration events, and `notification.write` / `notification.deliver` off
  `NotificationSent`, split on whether the channel is the in-app inbox.
- `App\Observers\LedgerEntryObserver` — `ledger.write` at `debug` for every
  ledger row, from the one place all three writers pass through.
- `config/logging.php` trimmed to `stdout` (the default in every environment),
  `null`, and `emergency`. `.env.example` sets `LOG_CHANNEL=stdout`.
  `phpunit.xml` sets it to `null` so the suite's output stays readable; the
  tests that read log lines capture them through the same formatter.
- `Tests\CapturedStory` — a Monolog handler carrying `StoryFormatter`, swapped
  behind the `Log` facade, so a test reads the JSON a reader would.
  `lines()`, `linesFor()`, `line()`, `values()`, `outline()`, `raw()`.
- `Tests\TestCase::setUp()` drops any unit of work a previous test left open.

### Event to action

| Event | Emitted by |
| --- | --- |
| `http.request` | `LogRequestStory` |
| `magic_link.request` | `SendMagicLink` |
| `magic_link.consume` | `MagicLinkVerificationController` (three refusal paths: no row, expired, used) |
| `customer.merge` | `MergeAnonymousCustomer` |
| `listing.create` | `CreateListing` |
| `listing.update` | `UpdateListing` |
| `listing.publish`, `listing.transition` | `ListingStatusController` — `transition` carries `status_from`/`status_to`, `publish` when the target is `for_sale` |
| `listing.view` | `Shop\ListingController` |
| `cart.add`, `cart.update` | `AddToCart` — `add` for a new line, `update` when an existing line is raised |
| `cart.remove` | `RemoveFromCart` |
| `order.place` | `PlaceOrder` |
| `order.pay` | `FinalizeOrder` — `did` on approval, `refused` with `decline_reason` on decline |
| `fulfillment.ship` | `MarkShipped` |
| `fulfillment.deliver` | `ConfirmDelivered` |
| `ledger.write` | `LedgerEntryObserver` |
| `payout.run`, `payout.pay` | `RunWeeklyPayout` |
| `conversation.open` | `OpenConversation` |
| `message.post` | `PostMessage` |
| `faq.publish`, `faq.unpublish` | `PublishListingFaq`, `UnpublishListingFaq` |
| `notification.write`, `notification.deliver` | `LoggingServiceProvider` on `NotificationSent` |
| `moderation.block_customer` | `BlockCustomer` |
| `moderation.lift_customer_block` | `LiftCustomerBlock` |
| `migrate.run`, `migrate.apply` | `LoggingServiceProvider` on the migration events |
| `seed.run` | `DatabaseSeeder` |
| `app.boot`, `app.shutdown` | `LoggingServiceProvider` |

### Deferred, with the ticket that emits them

| Event | Ticket |
| --- | --- |
| `order.cancel`, `order.sweep`, `fulfillment.decline`, `refund.issue` | FEAT-020 — order lifecycle back half |
| `rate_limit.exceed` | FEAT-021 — configurable rate limits |
| `moderation.remove_listing`, `moderation.lift_listing_removal` | FEAT-024 — admin moderation of listings |

### Deviations from §2, and why

- **`listing.view` is one line per view.** §2.3 collapses it to once per
  (listing, customer, hour) and logs the collapse as `refused` at `debug`.
  This prototype has no collapse yet — `RecordListingEvent` writes a row per
  view — so the log follows the code. `StoryEvent::refusalLevel()` already
  returns `debug` for it, so the collapse only has to call `refused()` when
  FEAT-023's roll-up lands.
- **A validation failure is not logged as `refused`.** §2.2 counts one as a
  domain refusal. Here a form request refuses before the action's story opens
  (`ChangeListingStatusRequest` admits only the transitions the current status
  allows), so there is no open story to end. The `http.request did` line
  carries the 302 or 422. A refusal the *core* raises — a
  `DomainRuleViolation`, an illegal transition, a declined card — does get its
  `refused` line.
- **`magic_link.request` has no `refused` path.** §2.3 names two — an address
  that is not admitted, and a rate limit — and this prototype has neither. The
  rate-limit refusal arrives with FEAT-021.
- **A framework line falls back to `event: app.log`.** §2.1 marks `event` and
  `phase` as always present, and the framework logs deprecations and its own
  errors without either. The fallback keeps every line a valid payload rather
  than letting a prose line through.
- **`http.request` carries no `txn_id`.** §2.1 scopes `txn_id` to "an action's
  transaction", and §2.2's own example shows the request lines without one.
- **`app.boot` and `app.shutdown` fire once per request**, because
  `artisan serve` runs one request per process. That is the PHP process
  lifecycle honestly reported; Node and Rails will fire them once per process
  and so far less often.
- **Two gaps in "every request".** A request that never matches a route (404)
  and one refused by `ValidateCsrfToken` (419) get no `http.request` lines: the
  middleware is appended to the `web` group, which is what lets it read the
  `sid` cookie after decryption and name the actor from a guard after the
  session starts. Prepending would cost both.
- **Seeder runs write no `ledger.write` lines.** `DatabaseSeeder` uses the
  framework's `WithoutModelEvents`, so no observer fires. The `seed.run` story
  still brackets the run.

### Verified by hand

`docker compose run --rm -p 8100:8000 app php artisan serve`, then a listing
page and a cart add over `curl`:

```json
{"ts":"2026-08-24T03:26:17.396Z","level":"info","event":"http.request","phase":"will","msg":"GET /art/portrait-of-a-welder","request_id":"req_01M0RWVY9K2P86WWFA0MBKY837","session_id":"ses_01M0RWMMNT28PXC8GYKX82FCNS","actor_type":"customer","actor_id":"cus_01M0RWSMAKHTF9MK9KPY2AKKZ9","data":{"method":"GET","path":"/art/portrait-of-a-welder"}}
{"ts":"2026-08-24T03:26:17.400Z","level":"info","event":"listing.view","phase":"did","msg":"viewed a listing","request_id":"req_01M0RWVY9K2P86WWFA0MBKY837","session_id":"ses_01M0RWMMNT28PXC8GYKX82FCNS","actor_type":"customer","actor_id":"cus_01M0RWSMAKHTF9MK9KPY2AKKZ9","data":{"listing_id":"lst_01M0RWVXS5FYS5GN5A7Z4EBH4C","seller_id":"sel_01M0RWVXMM3DX703BDEAARFRVA","status":"for_sale"}}
{"ts":"2026-08-24T03:26:17.465Z","level":"info","event":"cart.add","phase":"will","msg":"adding a listing to the cart","request_id":"req_01M0RWVYB58CGM5H9SNKMTH87X","session_id":"ses_01M0RWMMNT28PXC8GYKX82FCNS","actor_type":"customer","actor_id":"cus_01M0RWSMAKHTF9MK9KPY2AKKZ9","txn_id":"txn_01M0RWVYBSNDTBQTAK2XE2QTTW","data":{"cart_id":"crt_01M0RWSMAWEG77TTA0NQT0J2SK","listing_id":"lst_01M0RWVXS5FYS5GN5A7Z4EBH4C","quantity":1}}
{"ts":"2026-08-24T03:26:17.471Z","level":"info","event":"cart.add","phase":"did","msg":"added the listing to the cart","request_id":"req_01M0RWVYB58CGM5H9SNKMTH87X","session_id":"ses_01M0RWMMNT28PXC8GYKX82FCNS","actor_type":"customer","actor_id":"cus_01M0RWSMAKHTF9MK9KPY2AKKZ9","txn_id":"txn_01M0RWVYBSNDTBQTAK2XE2QTTW","data":{"cart_id":"crt_01M0RWSMAWEG77TTA0NQT0J2SK","cart_item_id":"cti_01M0RWVYBWPQAVCTEB38Q7GDBX","listing_id":"lst_01M0RWVXS5FYS5GN5A7Z4EBH4C","quantity":1},"duration_ms":6}
{"ts":"2026-08-24T03:26:17.472Z","level":"info","event":"http.request","phase":"did","msg":"POST /cart/portrait-of-a-welder 302","request_id":"req_01M0RWVYB58CGM5H9SNKMTH87X","session_id":"ses_01M0RWMMNT28PXC8GYKX82FCNS","actor_type":"customer","actor_id":"cus_01M0RWSMAKHTF9MK9KPY2AKKZ9","data":{"status":302},"duration_ms":26}
```

The response carried `X-Request-Id: req_01M0RWMMNT28PXC8GYKX82FCNR` and a
`sid` cookie expiring `Tue, 24 Aug 2027`. `make fresh` wrote `migrate.run`,
`migrate.apply`, `seed.run`, and the domain lines of every seeded order.

### Gate

`make check` green: 1225 tests, 3293 assertions, 100.0 % of lines. Baseline at
`935877c` was 1147 tests, 2542 assertions.

### Review fix-ups

A review of `f9a06e4` found two blocking defects, three smaller ones, and
overturned one of the deviations above. All six are closed here.

**`Story::tell()` is how an action brackets its work.** `failed` had no
caller: every action caught only `DomainRuleViolation`, so an unexpected
throwable inside a `DB::transaction` left the unit of work without an ending
and its `txn_id` on `Story`'s stack, where only `Story::forget()` at the top
of the next request stopped it naming later lines. `Story::tell($message,
$data, $work)` now writes `will`, runs the work, and ends the unit whichever
way the work leaves: the work names its own `did`, a `DomainRuleViolation`
becomes `refused` at `info` and still reaches the caller, anything else
becomes `failed` at `error` carrying the exception and still reaches the
caller, and a `finally` closes the unit on every path — including work that
writes no ending of its own. Every action, `MagicLinkVerificationController`,
and `ListingStatusController` are rewritten onto it, which removed the
hand-written `refused` catch from each of them. `StoryTest` covers the three
endings and the empty stack after each; `MarkShippedTest` covers an action's
own `failed` line from a listener that throws inside the transaction.

**Correction to "A refusal the core raises … does get its `refused` line".**
It did not for `listing.transition`. `ListingStatusController` called
`$listing->changeStatusTo($next)` bare on the argument that the form request
had already restricted the field — true for the normal path, but a status
that moves between validation and the call throws `DomainRuleViolation` from
the core, and that refusal was logged as neither `refused` nor `failed` and
leaked the unit. The call now runs inside `tell()`, so the raced refusal ends
the story; `ListingStatusControllerTest` pins it.

**Correction to "Two gaps in 'every request' … Prepending would cost both".**
The deviation is overturned and the gap is closed. `LogRequestStory` is now
the outermost middleware in the application rather than one of the `web`
group's, and both a 404 and a 419 come back through it as ordinary responses:
`Illuminate\Routing\Pipeline` renders an exception into a response at the
stage that raised it, so route resolution's `NotFoundHttpException` and
`PreventRequestForgery`'s `TokenMismatchException` never escape as
exceptions. What running that early costs is the session and the guards, so
`NameRequestVisitor` — appended to the `web` group — now holds the `sid`
cookie and the actor naming and puts `session_id`, `actor_type`, and
`actor_id` in the context there. Every line from the group inward carries
them; the request's own `will` line, written before the group, carries
`request_id` alone.

**`X-Request-Id` on the failure path.** The header was set after the
`catch`'s rethrow, and a response the exception handler renders never passes
back through the middleware at all. The middleware leaves the id on the
request (`LogRequestStory::REQUEST_ID_ATTRIBUTE`) and `bootstrap/app.php`'s
`$exceptions->respond()` stamps it on every response the handler builds.

**The request-id bound is anchored.** `GIVEN_REQUEST_ID` was
`/^[A-Za-z0-9_-]{1,64}$/`; PHP's `$` matches before a trailing newline, so
`"trace42\n"` passed and was echoed verbatim into the response header. It is
`/\A[A-Za-z0-9_-]{1,64}\z/` now, and the dataset carries the trailing-newline
case.

**A comment named a ticket.** `Shop\ListingController` said the collapse
arrives with FEAT-023. It now says what the code does: every view writes its
own line, nothing collapses the repeat views one customer makes within an
hour.

### Verified by hand, after the fix-ups

`docker compose run --rm -p 8100:8000 app php artisan serve`, then an
unrouted path, a POST with no CSRF token, and a request with the database
file moved away:

```json
{"ts":"2026-08-24T04:05:24.694Z","level":"info","event":"http.request","phase":"will","msg":"GET /nothing-is-here","request_id":"req_01M0RZ3JJPW16XMH6SXSFQFP7A","data":{"method":"GET","path":"/nothing-is-here"}}
{"ts":"2026-08-24T04:05:24.701Z","level":"info","event":"http.request","phase":"did","msg":"GET /nothing-is-here 404","request_id":"req_01M0RZ3JJPW16XMH6SXSFQFP7A","data":{"status":404},"duration_ms":7}
{"ts":"2026-08-24T04:05:42.263Z","level":"info","event":"http.request","phase":"will","msg":"POST /logout","request_id":"req_01M0RZ43QPSDSYHM38JGDD49V8","data":{"method":"POST","path":"/logout"}}
{"ts":"2026-08-24T04:05:42.292Z","level":"info","event":"http.request","phase":"did","msg":"POST /logout 419","request_id":"req_01M0RZ43QPSDSYHM38JGDD49V8","data":{"status":419},"duration_ms":29}
{"ts":"2026-08-24T04:05:52.315Z","level":"info","event":"http.request","phase":"will","msg":"GET /","request_id":"req_01M0RZ4DHVVSH9FR476NXS9N3D","data":{"method":"GET","path":"/"}}
{"ts":"2026-08-24T04:05:53.854Z","level":"info","event":"http.request","phase":"did","msg":"GET / 500","request_id":"req_01M0RZ4DHVVSH9FR476NXS9N3D","data":{"status":500},"duration_ms":1538}
```

The 404 response carried `X-Request-Id: req_01M0RZ3JJPW16XMH6SXSFQFP7A` and
the 500 carried `X-Request-Id: req_01M0RZ4DHVVSH9FR476NXS9N3D`.

### Gate, after the fix-ups

`make check` green: 1247 tests, 3395 assertions, 100.0 % of lines. The
baseline at `6e88c09` was 1234 tests, 3302 assertions.
