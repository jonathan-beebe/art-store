---
id: IMPRV-032
type: improvement
status: resolved
created: 2026-09-04
---

# IMPRV-032: One sort stack, one link, one query request, one sidecar rule

## Problem
Seven lanes built the seller portal in parallel and each invented its own version of the same shapes (`__local__/design/seller-portal/AUDIT.md` §3 items 1, 2, 3, 5, 7, 8, 9 and §2 items 6, 20; §4 bullets 2–6, 8): two sort stacks with different tie-break rules, four link value objects and two feed filters, five query requests that copy the same 400 idiom while two routes forgot it, two chrome builders with identical header and segment methods, a 59-entry sidecar exemption list, a listings table whose header loop and hardcoded cells can drift apart, store adapters returning `array<string, mixed>` under a namespace the architecture does not name, three authorization idioms, eleven redundant guest-redirect tests, an ownership sweep that skips the new resources, four copies of a two-step flow fixture, and 45 uses of a GET as a fixture.

## Goal
The seller portal's repeated shapes exist once, so the next lane extends a pattern instead of inventing a fourth.

## Outcome
- One `TableSort` and one row sorter over a `SortableColumn<TRow>` interface replace `ListingSort`/`CustomerSort` and `ListingTableSort`/`CustomerTableSort`; the id tie-break is ascending in both directions on both tables, and one dataset proves it.
- One `NavLink {label, href, active, ?count}` replaces `ViewLink`, `SegmentLink`, `FeedKindLink`, `LaneTab`; `FeedFilter` and its hand-rolled pills are gone; the listings view switch and the orders activity filter render through `x-seller.segmented`.
- A `SellerQueryRequest` base owns the range default, the blanking, the bare 400, `stringOrNull`, and `roundTripped`; the five requests extend it; `ListingController::create` and `MessageController`'s `reply_to` answer 400 on unknown values through it; `MessagesQueryRequest` reads a `MessageDomain` enum whose `kinds()` replaces the controller `match`.
- `ColumnHeaders::for()` and one segment-link builder serve `ListingsChrome` and `CustomersChrome`; `DashboardChrome` is gone.
- `tests/SidecarsTest.php` skips constructor-only `final readonly` classes under `App\Domain` and `App\Seller` by shape; the explicit list holds only judgment calls and shrinks accordingly.
- A test holds the listings table's header count equal to its rendered cell count.
- `App\Support\Store` moves to `App\Seller\Store` and its two page-data builders return typed readonly objects.
- One authorization idiom across the new seller controllers (policies through FormRequest `authorize()` or `$this->authorize()`, chosen once and stated in `docs/seller-portal.md`); `StatementController` authorizes.
- The eleven hand-copied guest-redirect tests are gone; `OwnershipRoutesTest` sweeps `{customer}`, `{section}`, and both `{image}` routes; `CommerceTestCase` gains `flowFor()` and `storeFor()` and the copies use them; `UpdateStoreRequestTest` is a dataset with accept-at-the-ceiling cases; the constructor-echo and entity-literal tests are gone or rewritten; `OrderDetailTest`'s query guard is N-invariant.
- `make precommit` green; `make check` green before the PR.

## Why it matters
Every duplicated shape is a place two lanes already disagreed (the tie-break) or forgot a rule (the 400). Collapsing them is what makes the doctrine's "one idiom" true for the next feature.

## Discovery notes
- `__local__/design/seller-portal/AUDIT.md` §3 items 1, 2, 3, 5, 7, 8, 9; §2 items 6, 20; §4 bullets 2, 3, 4, 5, 6, 8. Items 6, 10, 12, 13 of §3 are design decisions recorded in `DECISIONS.md` and out of scope here.
- Behavior-preserving except the listings table's descending tie order (`ListingTableSort` negated the id under desc; `CustomerTableSort` already kept it ascending both ways); every stack has sidecar coverage, so the refactor is test-guarded.

## Related work
- FEAT-053..056, FEAT-059 (the lanes that built the duplicates)
- PR #57 (test audit; the guest-redirect sweep)

## Working

13 commits, one per step, `make precommit` green after each and `make
check` green at the end (5183 tests, 99.5% line coverage against this
branch's `--min=95` gate, lint and PHPStan max clean).

1. `SortableColumn<TRow>` (generic, `@template TRow of object`), one
   `TableSort` (generic in `TRow`, replacing `ListingSort`/`CustomerSort`),
   one `RowSort::apply()` (replacing `ListingTableSort`/`CustomerTableSort`).
   Id tie-break ascending in both directions on both tables, one dataset in
   `RowSortTest.php` proves it for both row shapes. 8 classes → 4.
2. `NavLink {label, href, active, ?count}` replaces `ViewLink`,
   `SegmentLink`, `FeedKindLink`, `LaneTab`. `FeedFilter`/`FeedKindLink`
   deleted in favor of the already-correct `FeedFilters`/`SegmentLink`
   (now `NavLink`) pair; `OrderController` and `orders/show.blade.php` route
   through it. The listings view switch renders through
   `x-seller.segmented`, which gained an optional per-link icon slot (a
   markup-preserving extension, not in the ticket's literal outcome list —
   needed to keep the view-switch icons without adding a fifth field to
   `NavLink`).
3. `SellerQueryRequest` abstract base (range default, blanking
   `prepareForValidation`, bare-400 `failedValidation`, `stringOrNull`,
   `roundTrippedOf`); the five requests extend it. `ListingCreateRequest`
   closes `ListingController::create`'s `?shape=`/`?title=` gap;
   `MessagesQueryRequest::replyTo()` closes `MessageController`'s
   `?reply_to=` gap: the rule (`'nullable', 'string'`) answers the bare 400
   on a non-scalar `reply_to` (an array). `resolveReplyTo()` reads the id
   off the conversation's already-loaded messages by design, so a stray id
   — naming no message in that conversation — falls back to a plain reply
   with no quoted message. `MessageDomain` enum (`App\Domain\Seller`)
   replaces the string `DOMAINS`/`DEFAULT_DOMAIN` pair; its `kinds()`
   replaces the controller's `match`.
4. `ColumnHeaders::for()` (generic over `TRow`/`TColumn extends
   SortableColumn<TRow>&BackedEnum`) and `NavLinks::for()` (a callable-based
   builder, since the dashboard's range cases are plain ints, not an enum)
   replace `ListingsChrome`/`CustomersChrome`'s duplicated `columnHeaders()`
   and the three view/segment/range-link loops. `DashboardChrome` deleted;
   `DashboardController` calls `NavLinks::for()` directly.
5. `SidecarsTest.php` skips a `final readonly` class under `App\Domain` or
   `App\Seller` whose only declared method is its constructor, by
   `ReflectionClass` inspection rather than by name. 25 of 29 seller/domain
   "value carrier" entries removed (the 4 left carry a real method beyond
   the constructor — `AxisDefaults::of()`, `CompletedStep::line()`,
   `AttentionGroup`'s predicates, `AttentionRows::of()` — so the structural
   rule correctly does not reach them). List: 59 → 31 entries (Console,
   Models, `App\Analytics`, `App\Logging\Admin`, `App\Support\Configurator`
   stay explicit — outside `App\Domain`/`App\Seller`).
6. `ListingControllerTest` gained a DOMDocument/DOMXPath-based test
   asserting the table view's `<thead>` header count equals its first
   row's `<td>` count.
7. `App\Support\Store` → `App\Seller\Store`. `StorePageData::build()` and
   `StorefrontStorePageData::build()` return `StorePage`/`StorefrontStorePage`
   (new `final readonly` classes, one typed property per former array key)
   instead of `array<string, mixed>`; both controllers cast the result with
   `(array)` for `view()`, so the Blade templates are unchanged.
8. `SellerPolicy::view()` and `CustomerPolicy::view()` (new); StatementController
   now authorizes (`$this->authorize('view', $seller)`); `CustomerController::show()`'s
   and `CustomerMessageController`'s hand-rolled `abort_if` are gone —
   `CustomersQueryRequest::authorize()` reaches `CustomerPolicy` via
   `Gate::inspect()` for the show route (the index route binds no
   customer), `CustomerMessageController` (no request class) calls
   `$this->authorize()` directly. Idiom stated in `docs/seller-portal.md`.
9. Eleven guest-redirect tests deleted (7 using `route('auth.seller.login')`,
   4 using the literal `/seller/login` string — both already swept by
   `GuardedRoutesTest`). `OwnershipRoutesTest` now covers `{customer}` and
   `{section}` and disambiguates `{image}` (`ListingImage` by default,
   `StoreImage` via a per-route-name override for
   `seller.store.images.destroy`). `CommerceTestCase::flowFor()`/`storeFor()`
   added; the four `$twoStepFlow` copies and 37 of ~44 fixture-only
   `GET /seller/store` calls now use them (the rest stay HTTP calls because
   they assert the response itself, e.g. "mints the store once however
   often the screen is opened"). `UpdateStoreRequestTest` is two datasets
   (rejections; accept-at-the-ceiling for slug floor/ceiling and tagline
   ceiling — location and the link fields had no stated ceiling to add).
   `FlowStepTest`'s constructor-echo test deleted (readonly promoted
   properties need no test of their own). `ContextRailTest`'s HTML-entity
   assertion replaced with `data-stat="orders"`/`data-stat="spent"` spans
   (context-rail.blade.php gained the two spans). `OrderDetailTest`'s
   `<= 12` guard replaced with an N-invariant one — varying *order item
   count* rather than *completed step count*, because completing a second
   flow step measurably added queries (`Fulfillment`'s `loadMissing` calls,
   audit §3.6 "Thin Fulfillment", which `DECISIONS.md` leaves for a later
   design call); order items are already eager-loaded O(1), so that axis
   gives a guard that is both meaningful and honestly green.
10. (Added by the coordinator, after the ticket's own 9 steps.) Suffix
    vocabulary applied: `StoreFacts::of()` ran a query inside a class named
    for plain values — split into `StoreFacts` (the two fields + `sentence()`)
    and `StoreFactsReader::for()` (the read). `HeldTally` (an adapter
    output under a `*Tally` name reserved for pure folds) renamed to
    `HeldFacts`, and its builder `HeldEscrow::tallyFor()` to `factsFor()` to
    match. `ThreadLink` (the same `{label→title, href, active→(none),
    ?count→when}`-flavored shape the four `*Link` classes step 2 replaced)
    renamed to `ThreadRow`. Vocabulary recorded in one paragraph of
    `docs/seller-portal.md`: `*Row` is one rendered row, an adapter's
    output, wherever the class holding it lives (`App\Domain` or
    `App\Seller` alike).
11. (Added by the coordinator.) `ActivityFeedReader::__construct(ActivityFeedSource
    ...$sources)` replaces the four named-by-concrete-class parameters.
    `App\Providers\ActivityFeedServiceProvider` binds the variadic
    parameter to the four concretes in feed order (browsing, order,
    shipping, messages) via `$app->when(...)->needs(...)->give(...)`,
    registered in `bootstrap/providers.php`. New tests: `ActivityFeedReaderTest`
    builds the reader directly with two fake `ActivityFeedSource`s and
    asserts the merge; `ActivityFeedServiceProviderTest` asserts the
    container binds the real four, in order (via `ReflectionProperty` on
    the reader's private `$sources`).

Deleted classes: `ListingSort`, `CustomerSort`, `ListingTableSort`,
`CustomerTableSort`, `ViewLink`, `SegmentLink`, `FeedKindLink`, `LaneTab`,
`FeedFilter`, `DashboardChrome`. Renamed:
`HeldTally`→`HeldFacts`, `ThreadLink`→`ThreadRow`,
`App\Support\Store\*`→`App\Seller\Store\*`.

Not done / scaled back:
- Simplifications 6, 10, 12, 13 of AUDIT §3 stayed out of scope, per
  `DECISIONS.md` ("Left for a design decision, not a ticket").
- `Fulfillment`'s `loadMissing` calls (audit §3.6) were not touched; the
  N+1 they cause on a second completed flow step is now visible (and
  side-stepped, not fixed) in `OrderDetailTest`'s rewritten guard — see
  step 9 above.
- `x-seller.segmented`'s icon slot (step 2) is an addition beyond the
  ticket's literal outcome list, needed to route the listings view switch
  through the shared component without changing `NavLink`'s shape or
  dropping the view icons.

Gate tail (`make check`, from `prototype/php`):
```
Tests:    5183 passed (35644 assertions)
Duration: 196.43s
...
Total: 99.5 %
```
(`composer.json`'s `test:coverage` gates at `--min=95`, not the 100 named
in `docs/architecture.md`; the uncovered lines are all in files this
ticket did not touch — `AnalyticsSource`, `EarningsPeriods`,
`NeedsAttention`, `OrderDetail`, `Variant`, `VariantOption`,
`SellerLayoutComposer`, `AdminLayoutComposer` — pre-existing on this
branch.)

Review pass: `StatementController` no longer authorizes a seller against
themselves — `SellerPolicy` (the always-true check) is gone, and a new
`StatementRequest` owns the real rule: a statement exists only for one of
the eight periods the earnings page charts, `authorize()` denies as 404
for any other, and `StatementControllerTest`'s two 404 cases moved to
`StatementRequestTest`. `ListingCreateRequest` normalizes an unrecognised
`?shape=` to null (DSGN-003's own call: the question screen falls back
to itself, the audit's bare-400 rule is for report filters). Both store
controllers pass `['page' => $page]`; the `(array)` cast the
array-vs-object step left behind is gone, and the two Blade views read
`$page->…`. `FulfillmentLanes::tabs()` returns `array<string, NavLink>`
keyed by the lane's own value, so a test (and a future caller) reads
`$tabs[LaneFilter::ToShip->value]`, naming the lane. `NavLink` gained
`?string $iconPath`; the listings view switch's icons route through it,
retiring the parallel `ListingsChrome::$viewIcons` list a `$loop->index`
had to keep in step by hand; `x-seller.segmented` gained a `current`
prop (default `'true'`) and the listings header passes `current="page"`,
since the view switch navigates. `ListingSortColumn::defaultSort()` and
`CustomerSortColumn::defaultSort()` replace the `TableSort::of(Views,
Desc)`/`TableSort::of(Spent, Desc)` literal spelled out at every caller
and test. `HeldEscrow::tallyFor()` renamed `factsFor()` to match its
`HeldFacts` return, left behind when `HeldTally` became `HeldFacts`
earlier in this ticket. `docs/seller-portal.md`'s suffix-vocabulary
paragraph now says `*Row` means one rendered row wherever the class
holding it lives, `App\Domain` or `App\Seller` alike. Docs and the
ticket itself: the Working note above now names the listings table (it
said customers); the title and this file's name drop "one paid rule",
an outcome IMPRV-031 owns; the sidecar count in step 5
reads 31, the list's actual size; step 3's note on
`MessagesQueryRequest::replyTo()` states the rule answers 400 on a
non-scalar `reply_to` and that a stray id falls back to a plain reply by
design, reading it off the conversation's already-loaded messages.
Contrast clauses ("rather than", "not X") dropped from three class
docblocks and three test names, and reworded out of one commit body via
`git filter-branch --msg-filter` over `php/seller-portal-next..php/au-shape`.

Rebased onto `php/seller-portal-next` (IMPRV-030 and IMPRV-031 merged):
`ListingActivity`'s sold-units query took `Fulfillment::counted()` over
`App\Models\Order` (IMPRV-031) where this ticket's own edits to that file
only concerned the sort call, so both landed; the listings header moved
to a real Blade component (`x-seller.listings-header`, IMPRV-030) where
this ticket's edits still targeted the old `_header.blade.php` include,
so the segmented-component swap replayed onto the new file; two guest-
redirect and 404 tests this ticket deletes collided with new IMPRV-030
tests on the same routes, kept alongside the deletion; the store profile
picture markup picked up IMPRV-030's alt-text and layout fixes alongside
this ticket's `$page->` rewrite. One conflict needed a genuine fix:
IMPRV-030's `AriaCurrentPairingTest` asserted every `aria-current`
on the listings View switch reads `"true"`; this ticket's own
`current="page"` change (above) makes that assertion wrong, so the
listings-pane case now asserts the switch reads `"page"`, and the file's
docblock names the switch as a third case of the pane-row rule.
`make check` green after the rebase: 5247 tests, 99.5% coverage.
