---
id: FEAT-018
type: feature
status: resolved
created: 2026-08-23
---

# FEAT-018: Prefixed ULID identifiers on every table

## Problem
Every domain table under `src/database/migrations/` uses `$table->id()` (autoincrement integer); URLs (`/orders/{order}`, `/admin/customers/{customer}`, `/seller/listings/{listing}`), order numbers, and thread paths expose sequential integers. `docs/alignment.md` §1 fixes the shared shape: text primary keys of the form `<prefix>_<26-char ULID>`, one prefix table across the three prototypes.

## Goal
Every row in the PHP prototype is identified, stored, and addressed by a prefixed ULID.

## Outcome
Every domain table's primary key and every foreign key is a text prefixed ULID per the §1 prefix table (framework tables keep their keys); route-model binding refuses a wrong-prefix or malformed id with the site's 404; the order number shown on the storefront, seller portal, and admin site is the order id; `make fresh` rebuilds a seeded database; seeds on the fixed clock produce ids that sort in creation order; factories produce valid ids; `docs/data-model.md` and the ER diagram show text ids; `make check` passes (PHPStan level max, 100 % coverage convention).

## Why it matters
Sequential ids leak volume and let anyone enumerate orders; a prefixed ULID is self-describing in a log line or a URL, and identical prefixes let a reader compare the three prototypes' logs and pages directly.

## Discovery notes
Idiomatic Laravel: `Str::ulid()` ships (Symfony Uid); a `HasPrefixedUlid` trait over `HasUniqueStringIds`/`HasUlids` with `newUniqueId()` prepending the model's prefix constant, and `resolveRouteBindingQuery` refusing the wrong prefix, keeps the change in one place per model. Migrations use `$table->string('id', 30)->primary()` and `foreignUlid`-style string FKs. Existing migrations may be rewritten in place — no data migration.

## Related work
- docs/alignment.md §1
- RFCTR-006 (strict models)

## Working

### What landed

- `App\Domain\Identifiers\PrefixedId` — the pure value object that spells and
  parses `<prefix>_<26-char uppercase Crockford base32 ULID>`. `parse()` takes
  the table's prefix and refuses anything else: another prefix, no prefix, a
  bare ULID, the wrong length, a lowercase body, a letter Crockford drops, a
  first character past the ULID time range, the empty string. Sidecar test
  covers each.
- `App\Models\Concerns\HasPrefixedUlid` — a trait over Laravel's
  `HasUniqueStringIds`, so `getKeyType()`, `getIncrementing()`, `uniqueIds()`,
  and route-model binding come from the framework. `newUniqueId()` mints from
  `Date::now()` through `Str::ulid()`; `isValidUniqueId()` defers to
  `PrefixedId::parse()`, which is what turns a wrong-prefix or malformed id
  into a `ModelNotFoundException` and so into the site's own 404.
- Each of the 20 domain models declares its prefix once, as
  `public static function idPrefix(): string`, the abstract method the trait
  requires. An abstract method rather than a constant because a trait constant
  cannot be redeclared by the using class, and PHPStan can hold a model to an
  abstract method.
- Every domain migration rewritten in place: `$table->string('id', 30)->primary()`
  and `$table->foreignUlid('<col>', 30)` with the cascade behaviour each column
  already had. `messages.sender_id` and `notifications.notifiable_id` are
  30-character strings beside their morph type columns. Every index and unique
  constraint kept, with the two that existed to order by id moved to a
  timestamp (below).
- `notifications` is in scope and carries `ntf_` ids.
  `App\Notifications\PrefixedUlidNotification` is an abstract base that sets
  `$this->id` in its constructor; `NotificationSender` honours an id the
  notification already holds. `ItemSold`, `OrderShipped`, and `MessageReceived`
  extend it. `MagicLinkIssued` does not: it is delivered to an address on the
  session or mail channel and never writes a `notifications` row.
- Ordering moved off the id everywhere it was the sort: storefront and seller
  listing lists, seller orders and earnings, admin sellers and customers,
  message threads, FAQ lists, `Conversation::latestMessage`,
  `Order::latestPayment`, `Customer::activeBlock`, `Customer::currentCart`,
  `Admin::platformAdmin`. The `messages_thread_index` moved from
  `(conversation_id, id)` to `(conversation_id, sent_at)`, and
  `listing_faqs_listing_index` from `(listing_id, id)` to
  `(listing_id, created_at)`.
- The order number on all three sites is the order id, rendered without a `#`
  — `Order ord_01J…` rather than `Order #ord_01J…`. Same for the admin site's
  unnamed-customer label and `ActorDisplay::nameOf()`.
- `ResolveCustomerFromCookie` reads the `customer_id` cookie through
  `PrefixedId::parse()` instead of `FILTER_VALIDATE_INT`.
- `PublishFaqRequest`'s `source_message_id` rule is
  `string` + `size:30` + the same scoped `exists`.
- Domain value objects that carry ids retyped `int` → `string`: `CartLine`,
  `CartTotals`, `CustomerIdentityPlan`, `ConversationSubject`,
  `ConversationKind::topic()`, `FaqPrefill`, `NotificationMessage`,
  `Purchaser`.
- A route test per site asserting that a wrong-prefix id, a bare ULID, a value
  of no shape at all, and an unknown id all answer the same 404
  (`/orders/{order}`, `/seller/orders/{fulfillment}`,
  `/admin/customers/{customer}`).
- `docs/data-model.md` gained an "Identifiers" section with the prefix table
  and the ordering rule; every id column in the ER diagram now reads `text`.
  `docs/architecture.md` and `docs/messaging.md` updated where they stated the
  id shape.

### Decisions §1 did not settle

- **`created_at` alone does not order rows.** Laravel stores timestamps at
  second resolution, and a seeder or a test writes many rows inside one
  second. Ordering by `created_at` alone would leave those ties to SQLite. So
  every ordering is `created_at` (or `sent_at` / `placed_at`, where that is
  what the page means) **with the id as the tiebreak** — a ULID sorts in the
  order it was minted, so the tiebreak is creation order. This keeps §1's rule
  that creation order is read from the timestamp; the id only separates rows
  the timestamp cannot.
- **Minting clock.** §1 says the id is minted "from the clock the action
  already receives". This prototype's actions take an explicit
  `DateTimeImmutable $moment`, but Eloquent mints a key inside `Model::save()`,
  which the action's moment never reaches. The trait mints from
  `Illuminate\Support\Facades\Date::now()` — the freezable application clock —
  so `travelTo`/`freezeTime` in a test moves the ids too. `make fresh` was
  checked: ids in `sellers`, `listings`, `orders`, and `messages` all sort in
  creation order.
- **Column type.** Primary keys are `string(30)` (`varchar`), foreign keys are
  `foreignUlid(col, 30)` (`char`, which SQLite reports as `varchar`), so the
  two read the same in the database. `foreignUlid` was chosen over a bare
  `string` because `->constrained()` and the cascade helpers live on the
  foreign-id column definition.
- **`sessions.user_id`.** The `sessions` table keeps the framework's own key,
  but the database session driver writes `Auth::id()` into `user_id`, and that
  is one of our ids. The column is a 30-character string rather than a
  `foreignId`.
- **`DatabaseNotification` route binding.** The notification rows are read
  back as the framework's own model, which we do not own and cannot give the
  trait. A wrong-prefix notification id therefore matches no row and answers
  404 on the same page, but through a missing row rather than through
  `PrefixedId`. The outcome §1 asks for holds; the mechanism differs for this
  one table.

### Left out

- The tables §1 names that this prototype does not have yet —
  `listing_removals`, `refunds`, `page_view_counts`, `rate_limit_windows`,
  `outbox_messages` — are out of scope. The tickets that create them follow
  the same shape.
- Framework tables (`sessions.id`, `cache`, `jobs`, `failed_jobs`,
  `job_batches`, migration bookkeeping) keep the framework's keys.
- The `sid` session cookie (`ses_`) and the `txn_id` log field (`txn_`) belong
  to §2 and the logging ticket, not here.

### Verification

- `make test`: 1147 tests, 2542 assertions, 100.0 % of lines. Baseline was
  1111 / 2503.
- `make lint`: Pint clean over 452 files; PHPStan level max, no errors, no
  baseline.
- `make fresh`: rebuilds and seeds; a served page renders
  `/art/{slug}` at 200, and `/orders/cus_01J…` and `/orders/nonsense` both
  answer 404.

### Review fix-ups

Closed three gaps a review of `935877c` found, against current `HEAD`
(after FEAT-019 landed):

- **Real delivery asserts the `ntf_` shape.** Added a test to
  `app/Notifications/ItemSoldTest.php` — `ItemSold`'s own sidecar, since
  `PrefixedUlidNotification` is abstract and carries no sidecar of its own —
  that sends a real, non-faked notification through the database channel
  (`$seller->notify(new ItemSold(...))`) and asserts
  `PrefixedId::parse('ntf', $notification->id)` is not null on the persisted
  row.
- **404-boundary coverage for the two `DatabaseNotification`-bound routes.**
  Added a Pest dataset test to `app/Http/Controllers/Seller/NotificationControllerTest.php`
  (`POST /seller/notifications/{notification}/read`) and to
  `app/Http/Controllers/Shop/AccountControllerTest.php`
  (`POST /account/notifications/{notification}/read`), each matching the
  shape of the three existing route tests: a wrong-prefix id, a bare ULID, a
  malformed value, and an unknown-but-well-formed `ntf_` id all answer 404.
- **Docs nit.** `docs/architecture.md` now lists `MessageReceived` alongside
  `ItemSold` and `OrderShipped` as extending `PrefixedUlidNotification`.

`make test`: 1234 tests, 3302 assertions, 100.0 % of lines (was 1225 / 3293
on `HEAD` before these fix-ups; +9 tests, +9 assertions). `make check`
green: Pint clean, PHPStan level max no errors, 100 % coverage.
