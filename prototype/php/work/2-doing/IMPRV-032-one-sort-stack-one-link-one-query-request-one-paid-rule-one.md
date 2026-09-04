---
id: IMPRV-032
type: improvement
status: doing
created: 2026-09-04
---

# IMPRV-032: One sort stack, one link, one query request, one paid rule, one sidecar rule

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
- Behavior-preserving except the customers table's descending tie order; every stack has sidecar coverage, so the refactor is test-guarded.

## Related work
- FEAT-053..056, FEAT-059 (the lanes that built the duplicates)
- PR #57 (test audit; the guest-redirect sweep)
