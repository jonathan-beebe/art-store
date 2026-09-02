# Art Store prototype — system architecture

Prototype of a two-sided art marketplace, served from three sites: a **seller
portal** (back office), a **customer storefront**, and an **admin site** (the
platform's own back office, for support and moderation). One Laravel
deployable, one SQLite file. Every page works with JavaScript off; a single
~20-line script is a progressive enhancement over the live unread badge (see
`docs/messaging.md` § "The live badge"). Every agent working in
`prototype/php/` reads this doc first and follows the conventions in it.

## Deployables

Question: what runs, and what talks to what?

```mermaid
flowchart LR
    subgraph docker["docker compose: app container"]
        laravel["Laravel app (PHP 8.3)\n/seller/* portal\n/admin/* site\n/ storefront"]
        sqlite[("SQLite\ndatabase/database.sqlite")]
        laravel --> sqlite
    end
    seller["Seller (browser)"] -- "HTML forms + EventSource" --> laravel
    customer["Customer (browser)"] -- "HTML forms + EventSource" --> laravel
    admin["Admin (browser)"] -- "HTML forms + EventSource" --> laravel
    mail["Email delivery (future)"] -.-> laravel
```

One container (`app`) holds PHP, Composer, Node (for the Tailwind build), and
the SQLite file. Nothing is installed on the host. Each site's browser also
holds one open `EventSource` per page against its own `/events` route — see
**The clock** and `docs/messaging.md` § "The live badge".

## Layers inside the deployable

Functional core / imperative shell. Dependencies point inward only.

```mermaid
flowchart TD
    entry["Entry: routes/*.php, app/Providers"] --> coord
    coord["Coordination: app/Http/Controllers, app/Actions, app/Console"] --> core
    coord --> adapters
    adapters["Adapters: app/Models (Eloquent), app/Notifications, resources/views"] --> core
    core["Core: app/Domain/** — pure PHP, no I/O, no clock, no random"]
```

| Layer        | Lives in                                                         | Rules                                                            |
| ------------ | ---------------------------------------------------------------- | ---------------------------------------------------------------- |
| Core         | `app/Domain/<Concept>/`                                          | Pure functions and immutable value objects. Every value object   |
|              |                                                                  | is `final readonly` with a private constructor and named         |
|              |                                                                  | factories (`Money::fromCents()`, `ShippingAddress::to()`,        |
|              |                                                                  | `CartLine::of()`); every static-only helper has a private        |
|              |                                                                  | constructor so it cannot be instantiated; enums answer questions |
|              |                                                                  | about themselves (`ListingStatus::isOnStorefront()`,             |
|              |                                                                  | `OrderStatus::awaitsPayment()`, `label()`) rather than being     |
|              |                                                                  | read from outside. Receives time/ids as parameters. Unit tested  |
|              |                                                                  | without doubles.                                                 |
| Adapters     | `app/Models/`, `app/Notifications/`, `app/Support/`,             | Eloquent models own their relations, casts, scopes, and the      |
|              | `app/View/Composers/`, `resources/views/`                        | writes that keep their own invariants — a model method applies a |
|              |                                                                  | decision the core made and writes the row (`Listing::sell()`,    |
|              |                                                                  | `Listing::changeStatusTo()`). Counts and sums a page shows are   |
|              |                                                                  | grouped in SQL by a scope or a model method                      |
|              |                                                                  | (`Listing::countedByStatus()`, `LedgerEntry::totalledByType()`,  |
|              |                                                                  | `Seller::escrowBalance()`), and the domain folds the rows that   |
|              |                                                                  | come back. Notifications and their channels carry a message out  |
|              |                                                                  | of the app; Blade views and the composers that fill a layout     |
|              |                                                                  | render it in.                                                    |
| Coordination | `app/Actions/<Feature>/`, `app/Http/Controllers/<Site>/`,        | Sequence core + adapters. An action that finishes a business     |
|              | `app/Http/Requests/<Site>/`, `app/Policies/`,                    | moment dispatches a past-tense event and a listener decides who  |
|              | `app/Console/Commands/`, `app/Events/`, `app/Listeners/`         | hears about it. Form requests are the typed entry for input:     |
|              |                                                                  | they authorize the bound model, validate, and hand the           |
|              |                                                                  | controller a domain object. Owns no domain `if`s — if one        |
|              |                                                                  | appears, extract to `app/Domain`. Covered by HTTP feature tests. |
| Entry        | `routes/web.php` → `routes/auth.php`, `routes/seller.php`,       | Wiring only. `AppServiceProvider::boot()` turns on               |
|              | `routes/shop.php`, `routes/admin.php`; `routes/console.php`;     | `Model::shouldBeStrict()` outside production (a lazy load, a     |
|              | `app/Providers`                                                  | discarded attribute, or a read of an unselected column raises),  |
|              |                                                                  | enforces the notification morph map, registers                   |
|              |                                                                  | `NotificationPolicy` for `DatabaseNotification` and the two      |
|              |                                                                  | event/listener pairs, binds `ShopLayoutComposer` to              |
|              |                                                                  | `components.layouts.shop`, `SellerLayoutComposer` to             |
|              |                                                                  | `components.layouts.seller`, and `AdminLayoutComposer` to        |
|              |                                                                  | `components.layouts.admin`, and registers `@visitorCan`.         |
|              |                                                                  | `bootstrap/app.php` turns listener discovery off, because it     |
|              |                                                                  | reflects over every file in `app/Listeners` including each       |
|              |                                                                  | listener's sidecar test. `routes/console.php` holds the          |
|              |                                                                  | schedule.                                                        |

Naming follows the `naming` skill: actions are verb phrases (`PlaceOrder`,
`ReleaseEscrow`), domain enums name states (`OrderStatus`), events are past
tense (`OrderPlaced`).

### Refusals

A rule the core refuses is an `App\Domain\DomainRuleViolation` — an illegal
status transition (`ListingStatus`, `OrderStatus`, `FulfillmentStatus`), a sale
the stock cannot cover (`ListingStock`), a cart line the listing no longer
supports (`CartQuantity`), an order with no items (`CartTotals`). Its message
is written for the person who tripped it. `bootstrap/app.php` maps it once, for
every route, to `back()->withInput()->withErrors(...)`, and all three layouts
render `$errors`; controllers therefore carry no pre-flight copy of a guard the action
already holds. `CheckoutController::place` is the one route that overrides the
destination: it sends the shopper to the cart, where the line the message names
is marked unavailable. Ownership stays separate — a row that is not the
visitor's is still a 404.

### The clock

`app/Domain` reads no clock (`tests/Arch.php` enforces it), so every instant
comes from the shell. `Controller::now(): DateTimeImmutable` is the one place
that produces it, and every controller calls it.

- Actions take `DateTimeImmutable $now` as their last parameter — the commerce
  ones (`PlaceOrder`, `FinalizeOrder`, `MarkShipped`, `ConfirmDelivered`,
  `AddToCart`, `ToggleFavorite`, `RecordListingEvent`, `RunWeeklyPayout`) and
  the identity ones (`SendMagicLink`, `SignInSeller`, `SignInCustomer`,
  `ClaimCustomerIdentity`) alike. No action calls `now()`.
- Model writes that stamp a time take it too: `MagicLink::consume($now)`,
  `MagicLink::statusAt($now)`. The exception is the framework's
  `DatabaseNotification::markAsRead()`, which reads `now()` itself — still
  frozen by `travelTo()`, but not handed in.
- `RunWeeklyPayouts` (the artisan command) is a second producer: a console run
  has no controller, so it reads `now()` or parses `--as-of`.
- `App\Support\UnreadCountStream` is the third. A held SSE stream outlives the
  single instant a request is answered at, so the controller computes a
  deadline from `Controller::now()` and the generator reads `now()` on each
  tick to compare against it. That read is in the shell, which is why
  `tests/Arch.php`'s clock rule — scoped to `App\Domain` — still holds.

A test freezes time with `travelTo()`/`freezeTime()` and every layer follows,
because one call per request produces the instant they all read. A stream is
the exception a test drives with `Sleep::fake(syncWithCarbon: true)`, which
advances the frozen clock by each faked sleep so the loop reaches its deadline
without waiting.

## Logging

`docs/alignment.md` §2 is the contract. Every log line is one JSON object on
stdout, in every environment: `config/logging.php` has one channel that writes
lines, `stdout`, and it is the default everywhere. `App\Logging\StoryFormatter`
is the Monolog formatter that spells the payload.

| Field                    | Always                             | Where it comes from                                              |
| ------------------------ | ---------------------------------- | ---------------------------------------------------------------- |
| `ts`                     | yes                                | the record's time, ISO-8601 UTC with milliseconds                |
| `level`                  | yes                                | `debug` \| `info` \| `warn` \| `error`                           |
| `event`                  | yes                                | `App\Logging\StoryEvent` — the §2.3 vocabulary                   |
| `phase`                  | yes                                | `App\Logging\StoryPhase`                                         |
| `msg`                    | yes                                | one sentence, present tense for `will`/`doing`, past for `did`   |
| `request_id`             | on requests                        | `LogRequestStory`, through `Log::withContext()`                  |
| `session_id`             | on requests, from the group inward | `NameRequestVisitor`, the same way                               |
| `actor_type`, `actor_id` | when known                         | the same, plus `ResolveCustomerIdentity` for a brand-new visitor |
| `txn_id`                 | inside a unit of work              | minted by `Story::will()`                                        |
| `data`                   | when useful                        | the small facts the line is about; every id is a prefixed id     |
| `error`                  | on `failed`                        | `{type, message}`, plus `stack` while `APP_DEBUG` is on          |
| `duration_ms`            | on `did`/`failed` after a `will`   | wall time since the `will` line                                  |

### The phases

`App\Support\Story` writes them. A unit of work opens with `will` and ends
exactly once — `did` when it happened, `refused` when the core turned it down
and the world is unchanged (`info`, because a rule held), `failed` when
something nobody planned for escaped it (`error`, carrying the exception).
`doing` marks a long step in between.

`Story::tell($message, $data, $work)` is how an action says all of that at
once: it writes `will`, runs the work, and ends the unit whichever way the
work leaves — the work names its own `did`, a `DomainRuleViolation` becomes
`refused`, anything else becomes `failed`, and both still reach the caller.
Every path closes the unit, so a `txn_id` cannot outlive the work that minted
it and name a later line.

```mermaid
sequenceDiagram
    participant B as Browser
    participant M as LogRequestStory
    participant C as CheckoutController
    participant A as PlaceOrder
    participant L as stdout

    B->>M: POST /checkout
    M->>L: http.request will — request_id
    M->>C: next() — through NameRequestVisitor, which adds session and actor
    C->>A: place the order
    A->>L: order.place will — txn_id minted
    A->>L: order.place did — txn_id, duration_ms
    A-->>C: order
    C-->>M: 302
    M->>L: http.request did — status, duration_ms
    M-->>B: 302 + X-Request-Id
```

### Two middlewares, not one

`LogRequestStory` is the outermost middleware in the application, ahead of
every group. A request that matches no route and one the forgery guard
refuses never reach a group, and a 404 is the line an operator goes looking
for first; both come back through it as an ordinary response, because the
framework's pipeline renders an exception into one at the stage that raised
it. Running that early costs the session and the guards, so `session_id`,
`actor_type`, and `actor_id` are added by `NameRequestVisitor`, appended to
the `web` group where cookies are decrypted and the session has started.
Every line from there on carries them; the request's own `will` line, written
before the group, carries `request_id` alone.

A request that ends in an exception is answered by the exception handler
rather than by the middleware that opened it, so `bootstrap/app.php` stamps
`X-Request-Id` on the handler's response from the id the middleware left on
the request.

### The three ids

- `request_id` (`req_…`) is one HTTP request. It goes back in the
  `X-Request-Id` response header, and an incoming header is honoured only when
  it matches `^[A-Za-z0-9_-]{1,64}$`.
- `session_id` (`ses_…`) is one browser. It lives in the `sid` cookie for a
  year, is minted on the first response a browser gets, and neither sign-in nor
  sign-out changes it. It is separate from `customer_id`, which names the
  visitor rather than the browser.
- `txn_id` (`txn_…`) is one unit of work. `Story::will()` mints it, and every
  line written before that story ends carries it — the action's own, and the
  ledger entries and notifications that fall out of it. A request is not a unit
  of work, so `http.request` carries none. All three are minted by
  `App\Support\IdMint`, the same value object every row's key comes from.

### What this prototype emits

`http.request`, `magic_link.request`, `magic_link.consume`, `customer.merge`,
`listing.create`, `listing.update`, `listing.publish`, `listing.transition`,
`listing.view`, `cart.add`, `cart.update`, `cart.remove`, `order.place`,
`order.pay`, `order.cancel`, `order.sweep`, `fulfillment.ship`,
`fulfillment.deliver`, `fulfillment.decline`, `refund.issue`, `ledger.write`,
`payout.run`, `payout.pay`, `conversation.open`, `message.post`,
`faq.publish`, `faq.unpublish`, `notification.write`, `notification.deliver`,
`moderation.block_customer`, `moderation.lift_customer_block`, `migrate.run`,
`migrate.apply`, `seed.run`, `app.boot`, `app.shutdown`.

`rate_limit.exceed` comes from `RateLimitGate` at `warn`;
`moderation.remove_listing` and `moderation.lift_listing_removal` come from
the two actions behind the admin's removal routes.

Domain events are emitted by the action that does the work. Three are not:
`ledger.write` comes from `LedgerEntryObserver`, so all three writers of a
ledger entry are covered by one place; `app.boot`, `app.shutdown`,
`migrate.run`, `migrate.apply`, `notification.write`, and
`notification.deliver` come from `LoggingServiceProvider`, which listens for
the framework events that already announce them.

### Redaction

No cookie value, magic-link token, card number, or email address reaches
`data`. An actor's id identifies them. `LogRequestStory` runs before the
router has resolved anything, so it matches the magic-link path itself and
logs `GET /auth/magic/<token>` as `/auth/magic/{token}`. `CheckoutControllerTest` and
`SendMagicLinkTest` each assert it over the whole captured log.

The same rule covers the session: a card number never reaches the old input a
re-rendered form reads (`ShopRequest::CARD_FIELDS`, and `bootstrap/app.php` for
the validation redirect and a `DomainRuleViolation`'s `back()->withInput()`).

### Reading the log in a test

`Tests\CapturedStory` swaps a Monolog handler carrying the same
`StoryFormatter` behind the `Log` facade, so a test reads the JSON a reader
would: `lines()`, `linesFor($event)`, `line($event, $phase)`, `outline()` for
the story in order, and `raw()` for the assertions that something appears
nowhere. `phpunit.xml` points `LOG_CHANNEL` at `null` so the suite's own output
stays readable.

## Rate limits and security headers

docs/alignment.md §3 fixes seven limits, one env variable each, read at boot:
`App\Domain\RateLimiting\RateLimitValue::parse()` turns `"<count>/<window>"`
(or `"off"`) into a budget, and `config/rate_limits.php` calls it once per
`App\Domain\RateLimiting\RateLimitName` case while the config file loads — a
malformed value throws there, before the process ever answers a request.
Both are pure: no `Illuminate`, no clock, no random.

`App\Support\RateLimiting\RateLimitGate` is the one place a limit is checked
and, if it holds, hit: it wraps `Illuminate\Cache\RateLimiter` over the
default cache store (`database`, so a count survives a restart the way an
in-memory one would not), and `checkEach()` looks at every key before
recording a hit against any of them — `magic_link_request`'s email and ip
budgets each trip on their own count, independently, and a request refused
on one key leaves no mark against the other. A trip throws
`App\Domain\RateLimiting\RateLimitExceeded` after writing the
`rate_limit.exceed` line itself (`warn`, `data.limit`, `data.key`,
`data.retry_after_seconds`) — the log line, the throw, and (through the
key) the redaction all happen in the one place, so no caller can do one
without the other.

A limit's key never reaches the log as an email address: the caller hashes
it first (`'email:'.hash('sha256', EmailNormalizer::normalize($email))`)
before it ever reaches the gate, so the gate has nothing to redact and the
cache key and the logged key are the same hash. Every other key — a
prefixed id, an ip — is already safe to log under docs/alignment.md §2.1.

Where each limit is checked runs ahead of the write it guards, inside the
action that would otherwise perform it, so a trip leaves no side effect:

| Limit                | Checked in                                                                    | Key                                         |
| -------------------- | ----------------------------------------------------------------------------- | ------------------------------------------- |
| `magic_link_request` | the three login `send()` methods; `CheckoutController::place()` for a guest's | `email:<hash>` and `ip:<ip>`, independently |
|                      | implicit link                                                                 |                                             |
| `magic_link_consume` | `MagicLinkVerificationController`                                             | `ip:<ip>`                                   |
| `message_post`       | every `MessageController::store()`; `Admin\SellerMessageController` and       | the poster's own id                         |
|                      | `Admin\CustomerMessageController`                                             |                                             |
| `conversation_open`  | `ListingQuestionController`, `SupportController` (shop and seller),           | the opener's own id                         |
|                      | `OrderMessageController` (shop and seller)                                    |                                             |
| `checkout`           | `CheckoutController::place()`                                                 | the customer's id                           |
| `payment_attempt`    | `OrderPaymentController::pay()`                                               | the order's id                              |
| `listing_write`      | `Seller\ListingController::store()` and `update()`                            | the seller's id                             |

On a trip: HTTP 429, `Retry-After: <seconds>`, one `rate_limit.exceed` line,
no side effect. The response body splits two ways. A route the visitor
reached by filling in a field catches `RateLimitExceeded` itself and
re-renders the page that field sits on — the three sign-ins, checkout, pay,
the seller's listing create/edit, every message `store()` (shop, seller,
admin), the admin's two message forms on the seller and customer pages, and
the storefront's ask-the-seller form. Each flashes the input first, so the
`body` textarea and every other field come back filled from `old()`, and
each renders through `Controller::tooManyRequests()`, which shares a
`ViewErrorBag` holding the sentence under a synthetic key (`errors->any()`
shows it the way every other page-level refusal already does, field-less
because no real form field matches it). What is left is a route with no
field to give back — the shop's and seller's support and order-thread
buttons, and the magic-link verification GET — and it falls through to a
matching `$exceptions->render()` in `bootstrap/app.php`, which picks the
site by path the same way `redirectGuestsTo()` does and renders that site's
own `resources/views/errors/rate-limited-{shop,seller,admin}.blade.php`.

Client ip (`$request->ip()`) is the socket's own unless `TRUSTED_PROXIES` is
set, in which case `bootstrap/app.php` wires Laravel's `TrustProxies` from
it (a comma list of IPs/CIDRs, or `*`) and reads the first
`X-Forwarded-For` value instead. The forwarded proto and port are trusted
alongside it, so behind a TLS-terminating proxy `$request->isSecure()` and
every generated URL follow the scheme the visitor used; the forwarded host
is not trusted, since with `*` an attacker-supplied `X-Forwarded-Host`
would poison every generated URL, magic links included.

`App\Http\Middleware\SecurityHeaders` sits in the global middleware stack
(not the `web` group — a route that matches nothing still needs to answer
with these, the way `LogRequestStory` is global for the same reason) and
sets, on every response: `Content-Security-Policy` (`default-src 'self'`,
`img-src 'self' data:` for a listing's inline SVG placeholder, `form-action
'self'`, `frame-ancestors 'none'`), `X-Content-Type-Options: nosniff`,
`Referrer-Policy: strict-origin-when-cross-origin`, and, in production only,
`Strict-Transport-Security`.

## Sites

| Site          | URL prefix | Guard                                                     | Theme                                                     |
| ------------- | ---------- | --------------------------------------------------------- | --------------------------------------------------------- |
| Seller portal | `/seller`  | `seller` (session, provider `sellers`)                    | Stock Tailwind, system font, vanilla controls, dense and  |
|               |            |                                                           | tool-focused.                                             |
| Storefront    | `/`        | `customer` (session, provider `customers`) + anonymous    | Bright, open, white space, large imagery, brand recedes.  |
|               |            | customer cookie                                           |                                                           |
| Admin site    | `/admin`   | `admin` (session, provider `admins`)                      | Stock Tailwind, system font, tables and forms; the        |
|               |            |                                                           | platform's back office.                                   |

Each site has its own Blade layout, an anonymous component (`<x-layouts.seller>`,
`<x-layouts.shop>`, `<x-layouts.admin>` in
`resources/views/components/layouts/`), and its own route file. All three
layouts render the `<x-debug-alert>` component that shows any magic link
flashed to the session.

`admins` rows are seeded, never signed up: `/admin/login` issues a link only
for an address that already has one, and answers a submitted address the same
way whether or not it does. `App\Domain\Auth\ActorType::allowsPath()` keeps
each actor on their own site, so a customer's or a seller's link is never
followed to `/admin` and an admin's is never followed to `/seller`. An admin
blocks a customer with a reason (`customer_blocks`); `Customer::canShop()` is
the predicate `AddToCart`, `PlaceOrder`, and `FinalizeOrder` read through
`App\Domain\Customers\CustomerStanding`, so a blocked shopper lands back on
the page they submitted from with the reason while browsing, searching, and
favoriting stay open. See `docs/messaging.md` § "What a block does".

### Authorization

Every route binds its model (`Listing $listing`, `Fulfillment $fulfillment`,
`DatabaseNotification $notification`, `Order $order`; the storefront listing
binds by slug) and then authorizes it. `app/Policies` holds the rules:

| Policy               | Abilities                           | Actor                            |
| -------------------- | ----------------------------------- | -------------------------------- |
| `ListingPolicy`      | `view`, `update`                    | `Seller`                         |
| `FulfillmentPolicy`  | `view`, `update`, `ship`            | `Seller`                         |
| `FulfillmentPolicy`  | `confirmDelivery`                   | `Customer`                       |
| `OrderPolicy`        | `view`, `pay`                       | `Customer`                       |
| `NotificationPolicy` | `markRead`                          | `Seller` or `Customer`           |
| `ConversationPolicy` | `view`, `post`, `resolve`, `reopen` | `Seller`, `Customer`, or `Admin` |

Ownership denials are `Response::denyAsNotFound()`: a row that is not the
actor's answers 404, so an id outside their own is never confirmed to exist.

A write route backed by a form request authorizes inside that request's
`authorize()`, which returns the same policy `Response` a controller would have
raised. A form request runs before the controller, so the ownership answer
lands before any validation message can describe a row the actor cannot see.

`view` and `update` answer ownership alone — the whole authorization question a
request has to pass, since the action behind it holds the state rule and
phrases its own refusal (see **Refusals**). `ship` and `confirmDelivery` add
the state each form needs to be worth offering, and only the views ask them:
`@can('ship', $fulfillment)` in the seller portal, `@visitorCan` on the
storefront. A double submission therefore still lands on the form's page with
the domain's message rather than on a 403.

Who the actor is differs per site. `Authenticate::using('seller')` makes the
seller guard the default for the request, so seller controllers call
`$this->authorize(...)`, their form requests call `Gate::inspect(...)`, and
seller views use `@can`. The storefront visitor is
resolved by `ResolveCustomerIdentity` middleware rather than signed in on a
guard, so `ShopController::authorizeVisitor()` names them
(`Gate::forUser($this->visitor())`) and the `@visitorCan` Blade directive
registered in `AppServiceProvider` asks the same policies about the same
visitor.

`SellerController`, `ShopController`, and `Admin\AdminController` are the
three base controllers; each exposes the actor behind the request (`seller()`,
`visitor()`, `admin()`) as a non-null model. Most admin pages read nothing
scoped to the admin who is reading and extend the base controller directly;
the messaging ones need `admin()` to attribute a reply, a "handled by", or a
resolve to the signed-in admin, so they extend `AdminController` instead —
their reads are not scoped to that admin, since the desk sees every thread.
All of them extend `App\Http\Controllers\Controller`, which holds the clock
(see **The clock**).

## Identity

- Passwordless. A `magic_links` row holds a hashed token, an `email`, an
  `actor_type` (`seller` | `customer` | `admin`), `expires_at`, `consumed_at`, and an
  optional `redirect_to`.
- Delivery is a notification: `App\Notifications\MagicLinkIssued`, sent to the
  address rather than to a row (`Notification::route(...)`, an
  `AnonymousNotifiable`) because the person may have no row yet.
  `config/magic_links.php` names the channel: `session` is
  `App\Notifications\Channels\SessionFlashChannel`, which flashes the URL so
  all three layouts print it in a debug alert; `mail` is the framework's mail
  channel, which sends `MagicLinkIssued::toMail()`. An unknown channel raises.
- Customers: every visitor gets a `customers` row with `email = null`, id stored
  in an encrypted cookie `customer_id`. Verifying an email either claims that
  row (first time) or **merges** the anonymous row into the existing verified
  customer (favorites, cart, orders, listing events re-pointed; a
  `customer_merges` row records `anonymous_customer_id -> customer_id` so stale
  cookies resolve to the merged account). The merge decision is a pure
  function in `app/Domain/Customers`; the re-pointing is an action.
- Guest checkout = place order as the anonymous customer
  (`OrderStatus::PendingVerification`), then require verification before the
  order is finalized (charged). The card is never asked for on that first
  request — it is collected on `/orders/{order}/pay`, which the guest reaches
  by following the magic link's `redirect_to`. A verified customer collects
  the card in the same `/checkout` request instead.
- See `docs/identity.md` for the sign-in and verification-with-merge sequence
  diagrams and the `ResolveCustomerIdentity` flowchart.

## Commerce domain

Money is integer cents (`App\Domain\Money\Money`). Orders may span sellers;
fulfillment and escrow are tracked **per (order, seller)** in a `fulfillments`
row.

```mermaid
erDiagram
    sellers ||--o{ listings : owns
    customers ||--o{ favorites : has
    customers ||--o{ carts : has
    customers ||--o{ orders : places
    customers ||--o{ customer_blocks : blocked_by
    listings ||--o{ listing_events : records
    listings ||--o{ cart_items : held_in
    orders ||--o{ order_items : contains
    orders ||--o{ payments : attempts
    orders ||--o{ fulfillments : split_by_seller
    sellers ||--o{ fulfillments : ships
    fulfillments ||--o{ ledger_entries : produces
    sellers ||--o{ payouts : receives
    payouts ||--o{ ledger_entries : settles
    customers ||--o{ customer_merges : merges
```

`magic_links` and `notifications` are deliberately absent: neither holds a
foreign key to `sellers` or `customers`. A magic link matches on an `email`
string plus `actor_type`; a notification names its recipient with a morph type
and id. Full column list and both `customer_merges` foreign keys:
`docs/data-model.md`.

### Listing status

`draft → for_sale → sold`, plus `archived` from `draft`/`for_sale`, plus
`sold → for_sale`: a declined card (`FinalizeOrder`) restores the stock
`PlaceOrder` took, so a listing that sold out during a dead checkout goes
back on the storefront rather than staying stuck at `sold`. Only `for_sale`
listings appear on the storefront. Quantity defaults to 1; a sale decrements
it and `sold` is reached at 0. Source of truth:
`App\Domain\Listings\ListingStatus::transitions()`, verified by
`ListingStatusTest`.

### Order status and fulfillment status

State diagrams, the checkout-to-notification sequence, and a worked
walkthrough of `OrderStatus::fromFulfillments()` roll-up: `docs/orders.md`.
In short: every order starts at `pending_verification` (guest) or
`awaiting_payment` (verified customer) — neither status skips straight to
`paid` — then moves through `paid`/`payment_failed` to
`partially_shipped`/`shipped`/`delivered`. A fulfillment (order × seller)
moves `awaiting_shipment → shipped → delivered`.

### Escrow and payouts

- `ledger_entries` per seller: `held` (+amount on paid), `released` (on
  delivered; moves the amount from held to available), `paid_out` (−amount when
  included in a payout).
- Platform fee: 10% of item subtotal, taken at `held`. Seller net = subtotal −
  fee.
- Payout period = Monday–Sunday. `php artisan payouts:run {--as-of=}` creates
  one `payouts` row per seller for all `released` amounts not yet paid out, as
  of the end of the most recent completed week. Period math is pure
  (`App\Domain\Escrow\PayoutPeriod`). `routes/console.php` schedules it
  `weeklyOn(1, '02:00')` — the Monday after a period closes. `POST
  /admin/payouts` runs the same action for every seller; the seller portal
  offers no control that runs one.
- One query reads the whole ledger: `LedgerEntry::totalledByType()` sums
  `amount_cents` per (seller, type), and `LedgerBalance::from()` folds those
  summed movements. The payout run bounds it by `occurred_at <= period.end`;
  `Seller::escrowBalance()` leaves it unbounded.
- Ledger flowchart, `payouts:run` sequence diagram, and a worked $100 example:
  `docs/escrow.md`.

### Fake payment

`App\Domain\Payments\FakeCard::decide(string $number): CardDecision`:

| Number                | Decision                      |
| --------------------- | ----------------------------- |
| `4242 4242 4242 4242` | approved                      |
| `4000 0000 0000 0002` | declined: generic decline     |
| `4000 0000 0000 9995` | declined: insufficient funds  |
| anything else         | declined: invalid card number |

Spaces and dashes are ignored. Only the last four digits are stored.

### Notifications

A business moment is an event, and what someone is told about it is a
notification:

```mermaid
flowchart LR
    finalize["FinalizeOrder"] -- "OrderPaid" --> sale["NotifySellerOfSale"]
    ship["MarkShipped"] -- "FulfillmentShipped" --> shipment["NotifyCustomerOfShipment"]
    sale -- "ItemSold" --> seller["Seller (Notifiable)"]
    shipment -- "OrderShipped" --> customer["Customer (Notifiable)"]
    seller --> inbox[("notifications")]
    customer --> inbox
```

- Events (`App\Events\OrderPaid`, `App\Events\FulfillmentShipped`) are
  `final readonly`, carry the model plus the instant, and are dispatched from
  inside the action's transaction.
- Listeners (`App\Listeners\NotifySellerOfSale`,
  `App\Listeners\NotifyCustomerOfShipment`) implement
  `ShouldHandleEventsAfterCommit`, so a rolled-back transaction tells nobody
  and no delivery runs with the transaction still open.
- Notifications (`App\Notifications\ItemSold`,
  `App\Notifications\OrderShipped`, `App\Notifications\MessageReceived`)
  extend `App\Notifications\PrefixedUlidNotification`, which gives each one a `ntf_`
  id before it is sent so the row carries the platform's id shape rather than
  the framework's UUID. `via()` reads
  `config('notifications.channels')` — `database` alone by default, with
  `mail` a comma away; `toArray()` and `toMail()` both come from
  `App\Domain\Notifications\NotificationMessage`, so the inbox row and the
  email say the same thing.
- `Seller`, `Customer`, and `Admin` are `Notifiable`. Rows land in Laravel's
  `notifications` table (`ntf_` id, `type`, `notifiable_type`/`notifiable_id`,
  `data` json, `read_at`) and are read back as
  `Illuminate\Notifications\DatabaseNotification`. `notifiable_type` holds the
  morph alias `seller`, `customer`, or `admin`, which is what
  `App\Domain\Auth\ActorType` names; `AppServiceProvider`
  enforces that one map for both `notifications.notifiable_type` and
  `messages.sender_type`.
- Each site renders its own inbox from `$notification->data`, counts
  `unreadNotifications()`, and marks one read with `markAsRead()`.

## Testing

- Pest, `it()`/`test()` functions with `beforeEach`, no PHPUnit classes
  outside `tests/*TestCase.php`. Tests are **sidecars**: `Foo.php` →
  `FooTest.php` in the same directory. `phpunit.xml` scans `app/`, `routes/`,
  and `database/` for `*Test.php` (the last one added for the seeders under
  `database/seeders/`) and lists `tests/Arch.php` by name, since the layer
  rules carry no `Test.php` suffix. `tests/TestCase.php` stays as the Laravel base.
- `tests/Pest.php` binds each sidecar directory to the base class its test
  files need: `Tests\CommerceTestCase` for `app/Actions`,
  `app/Console/Commands`, `app/Events`, `app/Http/Controllers/Admin`,
  `app/Http/Controllers/Seller`, `app/Http/Requests/Admin`,
  `app/Http/Requests/Seller`, `app/Listeners`, `app/Models`,
  `app/Notifications`, `app/Policies`, `app/Providers`, and `app/Support`;
  `Tests\StorefrontTestCase` for
  `app/Http/Controllers/Shop`, `app/Http/Requests/Shop`,
  `app/View/Composers`, `tests/SmokeTest.php`, and
  `tests/ConfiguratorSmokeTest.php`;
  `Tests\TestCase` alone for `routes/`;
  `Tests\TestCase` + `RefreshDatabase` for `app/Http/Controllers/Auth`,
  `app/Http/Middleware`, `app/Http/Requests/Auth`, and `database/seeders`.
- Every model under `app/Models` has a factory under `database/factories`,
  with a state per meaningful status (`OrderFactory::paid()`,
  `FulfillmentFactory::shipped()`, `PaymentFactory::declined()`,
  `MagicLinkFactory::consumed()`, `ListingFactory::archived()`, and the like).
  A test reaches for `Model::factory()->create([...])` for a plain row and for
  the action walk (below) only when the row's *lifecycle* — not just its
  final shape — is what the test is about.
- A repeated fixture is a public method on `Tests\CommerceTestCase`
  (`cartWithOneListing()`, `paidOrderWithTwoSellers()`,
  `shippedFulfillmentFor()`, `deliveredFulfillmentFor()`); a fixture used by
  one file is a closure declared at the top of that file and pulled into each
  test with `use ($fixture)`, reaching the running test case through `test()`.
- Tabulated input/output shapes are datasets: inline `->with([...])` or a
  file-local `dataset()` call at the top of the sidecar. Named datasets
  declared in `tests/Pest.php` do not resolve from sidecars under `app/`
  (dataset scope is `tests/`), so the suite keeps datasets inline or
  file-local instead of a shared library.
- `tests/Arch.php` enforces the layer rules: `App\Domain` uses nothing from
  `App\Models`, `App\Http`, `App\Actions`, `App\Console`,
  `Illuminate\Database`, or facades, and calls no clock/random functions;
  every class under `App\Actions` is final and invokable; controllers do not
  use the `DB` facade; no debug functions anywhere; `env()` only in
  `config/`, never under `App`; every file declares strict types — plus
  Pest's `laravel` and `security` presets. The preset's `ignoring` list names
  one class at a time rather than a namespace: the ten controllers whose
  route methods are action verbs (`CartController::add`,
  `CheckoutController::place`, `FavoriteController::toggle`,
  `OrderPaymentController::pay`, `AccountController::readNotification`,
  `NotificationController::markRead`,
  `SignOutController::seller`/`customer`/`admin`, and the three
  `LoginController::send` pairs), `App\Domain` for enums that live
  beside the concept they model, `App\Console\Commands\RunWeeklyPayouts` for
  its artisan command name, `App\Notifications\Channels` for a delivery
  channel, which is not itself a notification, and
  `App\Http\Requests\Shop\ShopRequest`, the abstract base whose children
  hold the rules. Every other controller is held to the preset's REST method
  vocabulary.
- `tests/SidecarsTest.php` asserts every non-abstract, non-interface,
  non-enum, non-trait class under `app/` has a sidecar, against a maintained
  list of exceptions (classes covered by another file's tests, or with no
  independently testable behavior); a second assertion fails if any
  exception's sidecar now exists, so the list can only shrink.
- One exception to the sidecar rule: `tests/SmokeTest.php` (the backbone
  walk) and `tests/ConfiguratorSmokeTest.php` (the two configurator walks)
  have no production file to sit beside. Both run inside the `Smoke`
  testsuite (`make smoke`) and inside `make test` as well.
- Core tests (`app/Domain/**`) need no app boot, no database, no doubles.
- Coordination tests (controllers, actions, commands) run against in-memory
  SQLite; they drive HTTP and assert on rendered HTML and database state.
  `Event::fake([...])` and `Notification::fake()` cover "something was sent";
  what an inbox shows is asserted through the page that renders it.
- Accepted concurrency gap: the suite runs on `sqlite :memory:` with a single
  connection, so every `lockForUpdate()` and rate-limit-atomicity claim is
  pinned by SQL shape only — a test asserts the compiled query contains
  `for update`, not that a second connection actually blocks on it. Real lock
  contention and concurrent rate-limit races cannot be exercised this way;
  closing that gap needs a database that supports concurrent connections
  under the test runner.
- Coverage via `pcov`: `composer test:coverage` (`make coverage`) runs the
  suite gated at 100% of lines (`--min=100`), prints a text summary, and
  writes `coverage/`. The suite covers 100.0% of the lines under `app/`.
- TDD: write the failing sidecar test, make it pass, refactor. Feature tickets
  are done when their flow has an HTTP test that walks it end to end.
- The gate: `make check` runs `lint`, then `assets`, then `coverage`,
  stopping at the first failure. `lint` runs Pint (`declare_strict_types`
  enforced tree-wide via the `laravel` preset), then PHPStan/Larastan at
  `level: max` over `app`, `database`, `routes`, and `tests` (model casts and
  config types understood via `parseModelCastsMethod` and `checkConfigTypes`),
  against the file tree only (`--no-deps`, no web server). `assets` builds
  the Tailwind CSS. `coverage` runs the full Pest suite under pcov, gated at
  100% of lines (`--min=100`). `make analyse` runs PHPStan alone.
- Sidecar tests are analysed at the same level as the code they cover: there
  are no `excludePaths`, no `ignoreErrors`, and no baseline. Pest reaches the
  test case, the custom expectations, and the arch DSL through traits and
  `expect()->extend()`, none of which static analysis can follow, so
  `src/phpstan/*.stub` declares them: `Pest\PendingCalls\TestCall` and
  `Pest\Support\HigherOrderTapProxy` mix in `Tests\StorefrontTestCase` (the
  deepest of the three base classes, so every file gets the members its own
  base carries), `Pest\Expectation` gains `toBeMoney()` and `toHaveStatus()`,
  and `pest-refs.stub` declares the classes those stubs name, which is what
  PHPStan's stub validator resolves against. A higher-order expectation chain
  (`expect($order)->status->toBe(...)`) resolves to `mixed`, so the suite
  writes `expect($order->status)->toBe(...)->and(...)` instead.

## Repository layout

```
prototype/php/
  README.md            how to run, serve, test
  docker-compose.yml   one service: app
  Dockerfile           php:8.3-cli dev/build + FrankenPHP runtime; composer + node + gd + pcov + sqlite
  Makefile             host-side wrappers over docker compose
  docs/                architecture + feature docs (this folder, indexed in docs/README.md)
  work/                tickets and journal (orchestration only)
  src/                 the Laravel application
```

## Mapping the project skills onto this stack

| Skill says                             | Here it means                                                                                             |
| -------------------------------------- | --------------------------------------------------------------------------------------------------------- |
| `npm run test:run -- <pattern>`        | `docker compose run --rm app composer test -- --filter <pattern>`                                         |
| Vitest unit test                       | Pest `it()` in a sidecar file, no app boot                                                                |
| React Testing Library integration test | Pest `it()` bound to `Tests\CommerceTestCase` or `Tests\StorefrontTestCase`, driving                      |
|                                        | `$this->get()/post()`                                                                                     |
| `src/`                                 | `prototype/php/src/`                                                                                      |
