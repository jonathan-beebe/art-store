# Admin site

What a platform operator does: read every seller, customer, listing, order and
fulfillment on the platform, moderate a listing or a customer they need to
stop, settle the money — cancel an unpaid order, refund a fulfillment, run the
weekly payout — and read what the platform's state, money, and traffic look
like from the front door.
Code: `app/Http/Controllers/Admin/`, `routes/admin.php`,
`resources/views/admin/`, `resources/views/components/admin/`,
`app/View/Composers/AdminLayoutComposer.php`, `app/Http/Middleware/
RollUpPageViews.php`, `app/Actions/Analytics/`, `app/Domain/Analytics/`.

Admins are seeded, never created — `database/seeders/AdminSeeder.php` — and
sign in through the same magic link sellers and customers use
([`identity.md`](identity.md)).

## Pages

| Path | Reads |
| --- | --- |
| `GET /admin` | tallies for every listing / order / fulfillment status (zero rows still listed), platform money, page views this week |
| `GET /admin/sellers` | every seller with listing and fulfillment counts, and the balance folded from one read of the ledger |
| `GET /admin/sellers/{seller}` | the seller's listings, fulfillments, payouts, and escrow balance |
| `GET /admin/customers?standing=all\|verified\|anonymous\|blocked` | every customer, anonymous rows included, with order, favorite and cart-line counts |
| `GET /admin/customers/{customer}` | orders, favorites, cart, block history, merge history, and the block / lift form |
| `GET /admin/listings?status=&seller=&removed=any\|removed\|visible` | every listing across every seller |
| `GET /admin/listings/{listing}` | the listing, its active removal and removal history, its view / favorite / cart-add counts, and every order line it sold on |
| `GET /admin/orders?status=&customer=` | every order with its customer, item count and total |
| `GET /admin/orders/{order}` | items, payment attempts, fulfillments, refunds, the cancel action and a refund form per fulfillment |
| `GET /admin/fulfillments?status=&seller=` | every fulfillment with its order and seller |
| `GET /admin/fulfillments/{fulfillment}` | the shipment, the lines it carries, its money, its ledger entries, and the refund or the form to issue one |
| `GET /admin/accounting` | per-seller reconciliation (held / available / paid out / refunded), fees earned and refunded at the platform level |
| `GET /admin/ledger?seller=&type=` | every ledger entry matching the filter, folded totals for that filtered set |
| `GET /admin/payouts?seller=`, `POST /admin/payouts` | payout history; run the weekly payout for every seller (`as_of` optional) |
| `GET /admin/stats` | page views by day (7-day window) and by route pattern, listing event tallies |
| `GET\|POST /admin/messages`, `/admin/messages/{conversation}` | the admin inbox ([`messaging.md`](messaging.md)) |
| `POST /admin/orders/{order}/cancel` | cancel an order nothing has been charged for; the stock goes back on the storefront |
| `POST /admin/fulfillments/{fulfillment}/refund` | refund one fulfillment with a reason; stock stays sold |
| `POST /admin/listings/{listing}/removals`, `.../removals/lift` | temporary or permanent removal with a reason; lift refused for a permanent one |
| `POST /admin/customers/{customer}/blocks`, `.../blocks/lift` | block with a reason; lift it |
| `POST /admin/sellers/{seller}/messages`, `POST /admin/customers/{customer}/messages` | open a thread from the directory |
| `GET /admin/events` | the admin's unread-count stream (`text/event-stream`) |

Every filter is optional and an empty value means "all": the console submits
`seller=` for "All sellers", and the controller reads it back with
`$request->filled(...)` (a string filter) or `$request->enum(...)` (a status),
both of which answer null for an absent, empty, or unrecognised value — so a
hand-typed `?status=nonsense` shows everything rather than an error page.

The `status` selects on the orders and fulfillments lists are driven by
`OrderStatus::cases()` and `FulfillmentStatus::cases()`, so `cancelled`,
`refunded`, and `declined` appear as filter values with no console change.

`StandingFilter` (`app/Domain/Customers/StandingFilter.php`) is the one filter
whose "all" is a value of its own, because the customers list offers it as a
choice: `standing=all`, `standing=` and no `standing` at all are the same page.

## One guard, one 404

Question: what stands between a request for an admin page and the row it names?

```mermaid
flowchart TD
    request["GET /admin/orders/ord_01J…"] --> group["Route group: prefix admin, middleware auth.admin"]
    group -->|"no admin session"| login["redirect to auth.admin.login"]
    group -->|"signed in"| binding["Route-model binding: Order::resolveRouteBinding"]
    binding --> parse{"HasPrefixedUlid::isValidUniqueId<br/>PrefixedId::parse('ord', value)"}
    parse -->|"wrong prefix, bare ULID, nonsense"| notfound["404"]
    parse -->|"an ord_ id"| lookup{"row exists?"}
    lookup -->|"no"| notfound
    lookup -->|"yes"| controller["Admin\\OrderController::show"]
    controller --> view["resources/views/admin/orders/show.blade.php"]
```

Caveats: the guard is on the group in `routes/admin.php`, never on a route — a
per-route check is one the next page added forgets. Every miss answers the same
404: an unknown id, an id carrying another table's prefix, and a value of no
shape at all are one page, so nothing reveals whether a thing exists. That
falls out of the route binding, which is why no admin page looks a model up by
hand.

## Balances are folded, never queried per seller

Question: how does `/admin/sellers` show a balance on every row without a
query per row?

```mermaid
sequenceDiagram
    participant Page as Admin\SellerController::index
    participant Entry as LedgerEntry::balancesBySeller
    participant DB as ledger_entries
    participant Fold as LedgerBalances / LedgerBalance
    participant View as admin/sellers/index.blade.php

    Page->>Entry: balancesBySeller()
    Entry->>DB: select seller_id, type, sum(amount_cents) group by seller_id, type
    DB-->>Entry: three rows per seller at most
    Entry->>Fold: LedgerBalances::from(movements by seller)
    Fold-->>Page: one LedgerBalance per seller
    loop each row on the page
        View->>Fold: of(seller.id)
        Fold-->>View: held / available / paid out, zero for a seller with no entries
    end
```

Caveats: the database sums each `(seller, type)` pair, so the fold sees three
rows per seller rather than the whole table, and the page costs one ledger read
whatever the seller count — `SellerControllerTest` counts the reads and holds
it to one. `LedgerBalances::of()` answers a zero balance for a seller with no
entries at all, which is what keeps the page from asking. The per-seller
`Seller::escrowBalance()` is still what the seller detail page reads: one
seller, one balance, one query.

Counts come from the database the same way — `withCount(['listings',
'fulfillments'])` on the sellers list, `withCount(['orders', 'favorites',
'cartItems'])` on the customers list, `withCount('items')` on the orders
list — rather than from counting a loaded collection in PHP.

## Filters as query scopes, tables as components

Each filter is a scope on the model it narrows, taking the filter value as
nullable and adding no clause when it is null: `Listing::ofStatus` /
`ofSeller` / `ofRemoval`, `Order::ofStatus` / `ofCustomer`, `Fulfillment::ofStatus` /
`ofSeller`, `Payout::ofSeller`, `Customer::inStanding`. A controller passes
what it read off the request straight through, so "empty means all" is one
answer in one place per model rather than an `if` in every controller.
`ofRemoval` takes `RemovedFilter` and treats `null` and the explicit `Any`
case alike — the third option the listings console offers reads the same as
no filter at all, the way `removed=`, `removed=any`, and no `removed` in the
query string are one page.

The repeated tables are anonymous Blade components under
`resources/views/components/admin/` — `listings-table`, `orders-table`,
`fulfillments-table`, plus the `filters` form and its selects. Each takes a
`showSeller` / `showOrder` / `showCustomer` prop, because the column that
names the owner is noise on the owner's own page.

## Tallies with nothing hidden

`/admin`'s listing, order, and fulfillment tallies come from `*::platformCountsByStatus()`
(`Listing`, `Order`, `Fulfillment`), each a `group by status` folded through
`*StatusTally::from()` (`App\Domain\Reports`) against the enum's full
`cases()`. A `group by` only answers for the statuses that have rows, and a
dashboard that hid `payment_failed` because nobody has hit it yet would be
lying about the state machine — so every status the enum names appears, at
zero if that is the true count. `/admin/stats`'s listing-event tally
(`ListingEventTally`) and the `admin.balance` tiles on the seller and platform
money sections follow the same rule.

Platform money — held, available, paid out, fees earned, fees refunded,
refunded — is `App\Domain\Escrow\PlatformMoney`, built from
`LedgerEntry::balancesBySeller()->total()` (every seller's balance folded
into one, free of a second ledger read — see `LedgerBalance::combine()`
below) and `Fulfillment::platformFees()` (`App\Domain\Escrow\PlatformFees`:
`isLive()` fulfillments have earned their fee, a declined or refunded one
forwent it). `/admin` and `/admin/accounting`'s totals row read the same
object. `/admin/ledger`'s totals are different on purpose: `LedgerBalance::from()`
over whatever rows the seller/type filter left, so a partial ledger reads as
a partial balance rather than the platform's.

## Page views, rolled up

Question: how does a request become a row on `/admin/stats`?

```mermaid
sequenceDiagram
    actor Visitor
    participant Kernel as Laravel kernel
    participant Roll as RollUpPageViews::terminate
    participant Countable as PageViewCountability::isCountable
    participant Site as PageViewSite::fromRoutePattern
    participant Record as RecordPageView
    participant Counts as page_view_counts

    Visitor->>Kernel: GET /art/nine-herons
    Kernel-->>Visitor: 200 text/html
    Kernel->>Roll: terminate(request, response)
    Note over Roll: a request that matched no route has no pattern — counted against nothing
    Roll->>Roll: pattern = "/" . request->route()->uri()
    Roll->>Countable: {method, statusCode, contentType}
    Countable-->>Roll: GET + 2xx + text/html
    Roll->>Site: fromRoutePattern("/art/{listing}")
    Site-->>Roll: shop (/seller and /admin claim their prefixes)
    Roll->>Record: __invoke(site, pathPattern, now)
    Record->>Counts: upsert (site, path_pattern, day, count=1)<br/>on conflict do update count = count + 1
```

Caveats: `RollUpPageViews` is appended to the **global** middleware stack in
`bootstrap/app.php`, not the `web` group, because a middleware added there
runs for every site regardless of which group's guard it sits behind, and the
site a hit belongs to is read back off the route's own pattern rather than
the request's host. It is terminable — `terminate()` runs after the response
has already gone back to the browser — so the roll-up costs the request it
counts nothing.

The pattern stored is `Route::uri()` with a leading slash, `/art/{listing}`
and never the concrete `/art/nine-herons`, so a thousand listing pages share
one row and the table grows with routes and days, not with traffic. The
unique index on `(site, path_pattern, day)` is what makes the first hit of a
day an insert and every later one an increment, in one upsert and no read
(`RecordPageView`).

"This week" on the dashboard is the seven days ending today
(`PageViewWeek::endingOn`), not Monday-to-Sunday: a calendar week reads as
almost nothing every Monday, and the number exists to be compared with the
day before it. The payout period is a calendar week and is a different
question — see [`escrow.md`](escrow.md).

## A view, collapsed to one per hour

Question: why does refreshing a listing page twenty times not write twenty
`listing_events` rows?

```mermaid
sequenceDiagram
    actor Visitor
    participant Controller as Shop\ListingController
    participant Record as RecordListingEvent
    participant Collapse as ListingViewCollapse
    participant Events as listing_events
    participant Story as Story::for(ListingView)

    Visitor->>Controller: GET /art/nine-herons
    Controller->>Record: (listing, customerId, View, now)
    Record->>Collapse: collapsesHourly(View)
    Collapse-->>Record: true
    Record->>Collapse: windowStart(now)
    Collapse-->>Record: the UTC hour containing now
    Record->>Events: exists (listing, customer, type, occurred_at >= windowStart)?
    alt already recorded this hour
        Events-->>Record: yes
        Record-->>Controller: null
        Controller->>Story: refused (level: debug)
    else first view this hour
        Events-->>Record: no
        Record->>Events: insert
        Record-->>Controller: the event
        Controller->>Story: did (level: info)
    end
```

Caveats: `favorite`, `unfavorite`, and `cart_add` are never collapsed — each
is a deliberate click, and `ListingViewCollapse::collapsesHourly()` answers
`true` for `view` alone. The window is the UTC hour containing the moment, not
a rolling hour from the first view — `ListingViewCollapse::windowStart()`
floors to the top of the hour, so a view at `14:59` and one at `14:01` share a
window but one at `15:01` does not. `StoryEvent::ListingView->refusalLevel()`
is `debug` rather than `info` (docs/alignment.md §2.3): an ordinary browsing
session would otherwise write an `info` refusal on every repeat view within
the hour and drown the story at the level an operator actually reads.

## What a removal or a block actually does

Question: an admin removes a listing or blocks a customer — what changes, and
where?

```mermaid
flowchart TD
    remove["POST /admin/listings/:id/removals<br/>RemoveListing(kind, reason)"] --> removalRow[("listing_removals row,<br/>lifted_at null")]
    removalRow --> availability["Listing::hasActiveRemoval -> isOnStorefront false"]
    availability --> browse["/ and search drop the listing (Listing::forSale)"]
    availability --> page["/art/:slug answers the same 404 as an unknown slug"]
    availability --> portal["/seller/listings/:id shows the reason;<br/>availableTransitions drops for_sale"]
    removalRow --> lift{"kind"}
    lift -- temporary --> lifted["canLift true:<br/>.../removals/lift sets lifted_at"]
    lift -- permanent --> refused["canLift false:<br/>the lift is refused"]

    block["POST /admin/customers/:id/blocks<br/>BlockCustomer(reason)"] --> blockRow[("customer_blocks row,<br/>lifted_at null")]
    blockRow --> standing["Customer::canShop -> false"]
    standing --> shopping["CustomerStanding::assertCanShop refuses<br/>cart add, checkout, and pay"]
    standing --> messages["a block removes posting a message; the reply form goes"]
    standing --> browsing["browsing, favorites, and reading threads stay open"]
```

Caveats: a listing with an active removal is off the storefront **whatever its
status** — `ListingAvailability::isOnStorefront(status, hasActiveRemoval)`
takes the removal as its second argument, `Listing::isOnStorefront()` is the
one place that asks it, and every page that turns a slug into a visible
listing goes through it (`Shop\ListingController`, `AskSellerRequest`). The
`forSale` scope that feeds browse and search excludes an active removal the
same way, because a removed listing keeps whatever `status` it held —
`for_sale` included — the removal changes nothing about the row underneath.
The storefront's 404 is one page for every miss (unknown slug, draft,
removed), so nothing reveals whether a thing exists.

The seller keeps their own page for a removed listing and reads the reason
there (`Listing::removalReason()`). `ListingAvailability::availableTransitions(status,
hasActiveRemoval)` is `ListingStatus::transitions()` with `for_sale` filtered
out while a removal stands, and it feeds both the status buttons on
`/seller/listings` and `ChangeListingStatusRequest`'s own validation — so a
seller cannot put a removed piece back on the storefront, and the refusal is a
422 on the status form rather than a rule the core has to catch.

At most one active removal per listing and one active block per customer.
Raising a temporary removal to a permanent one is lift then remove, which
leaves the seller one reason to read rather than two overlapping ones. Each
refusal is a `DomainRuleViolation`: `RemoveListing` on an already-removed
listing, `LiftListingRemoval` on a listing with nothing active or a
`permanent` removal, `BlockCustomer` on an already-blocked customer,
`LiftCustomerBlock` on an unblocked one.

Both lifts key off the **subject**, not the removal or block row —
`LiftListingRemoval` and `LiftCustomerBlock` both take the listing or the
customer, never the row itself — so a page that knows the listing or the
customer needs nothing else, and "which one is active" stays a single answer
in `Listing::currentRemoval()` / `Customer::currentBlock()`.

A blocked customer can still browse, favorite, and read their threads. What a
block removes is adding to a cart, checking out, paying, and sending
messages — which is why the predicate is named `canShop()` rather than after
the block.

## Payouts move to the admin site

`POST /admin/payouts` parses its own optional `as_of` field and calls the same
`RunWeeklyPayout` action `payouts:run` calls, for every seller rather than
one. `GET /admin/payouts?seller=` lists payout history, the same shape
`/admin/ledger` and the other admin lists use. The seller portal shows a
seller their held / available / paid-out balance and their payout history on
`/seller/earnings` and offers no control that runs one — the "run payouts"
debug button this prototype started with is gone. The full sequence and the
re-run rule are in [`escrow.md`](escrow.md).
