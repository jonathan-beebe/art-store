---
id: IMPRV-010
type: improvement
status: resolved
created: 2026-08-23
---

# IMPRV-010: Messaging and merge invariants learned from PHP and Rails

## Problem
`planConversation` decides find-or-open in app code only — two concurrent opens for the same subject create two threads (PHP has a unique `subject_key`, Rails a unique `COALESCE` expression index). `planCustomerMerge` re-points conversations wholesale, so a merge can leave one subject with two threads (PHP/Rails fold them), leaves `customer_blocks` on the anonymous row, and does not re-point `messages.sender_id`. `listing_faqs` has no uniqueness on `(listing_id, source_message_id)`, so publishing twice creates duplicate FAQ rows, and a thread shows no "published" marker.

## Goal
The database, and the merge, hold the same invariants in Node as in the other two prototypes.

## Outcome
One thread per subject is enforced by a unique index and the concurrent-open race is tested; merging folds duplicate conversations preserving per-message `read_at`, re-points blocks and sent messages, and a test asserts every `customer_id` column is either in the merge manifest or in an explicit left-behind list; `(listing_id, source_message_id)` is unique and a second publish is refused with the thread showing "Published to FAQ" with a link; docs updated.

## Why it matters
Two threads for one subject and duplicate FAQs are visible product bugs; the invariants are cheap once the shape is borrowed.

## Discovery notes
Rails' `COALESCE` expression index and PHP's `subject_key` both fit Kysely. The manifest test is the Rails `MERGED_ASSOCIATIONS` idea. Land after FEAT-018 so the index is written once over text ids.

## Related work
- RFCTR-002, RFCTR-004
- prototype/rails BUG-001 (merge duplicate threads)

## Working

### What landed

1. **`subjectKey(subject)`** — `src/app/core/messaging/conversation-subject.ts`,
   beside `isSameConversationSubject`. Pure: `kind` followed by a
   `<letter>:<id>` token for every non-null participant/subject column, walked
   in a fixed order (`sellerId:s, customerId:c, adminId:a, listingId:l,
   fulfillmentId:f`). Real example:
   `listing_question:s:sel_00000000000000000000000001:c:cus_00000000000000000000000002:l:lst_00000000000000000000000003`.
   Two equal subjects always produce the same string; two subjects
   `isSameConversationSubject` reads as different never collide — proven by a
   literal test over every kind, and reasoned in the function's own comment
   (same kind ⇒ same filled columns ⇒ a differing subject differs in at least
   one token; a prefixed id from one column can't equal another's).

2. **`conversations.subject_key`** — added to the `create-messaging` migration
   (rewritten in place, no backfill, per FEAT-018/019/020 practice) as
   `text notNull unique`. Chose PHP's plain unique column over Rails'
   `COALESCE` expression index, per the contract's decision — Node ids are
   text ULIDs, not integers, so a `COALESCE(..., 0)` sentinel would need a
   string that can never collide with a real id, and no migration in this
   codebase uses an expression or partial index yet.

3. **`openConversation`** now looks up the existing row by `subject_key`
   (replacing the old multi-column `where` scan — the same rule expressed once)
   and, on `'open'`, inserts with `.onConflict((oc) =>
   oc.column('subjectKey').doNothing()).returningAll()`. When the insert lands,
   that's the open (`wasReused: false`). When it conflicts — no row returned —
   the action re-reads by `subject_key` and returns that row as a reuse. This
   is the "upsert, then catch-and-reread on the miss" shape the ticket asked
   for, not a raw try/catch around a constraint error.

4. **`(listing_id, source_message_id)` unique index** on `listing_faqs`, plain
   (not partial) — SQLite already treats two `null`s in a unique index as
   distinct, so a hand-written FAQ with no source is unaffected.
   `publishListingFaq` checks first (`refuseUnlessUnpublished`, inside the same
   transaction) and throws `TransitionError('That question is already
   published to the listing.')` rather than letting the constraint reach the
   database — this is decision 4's "domain refusal the seller can see", surfaced
   as a flash on `/seller/listings/:id/faqs`, redirecting the same place a
   validation error does. A route-level `try/catch` mirrors the one `postMessage`
   already uses.

5. **`ThreadMessage.publishedFaqId`** (`conversationThread`) — looked up once
   per thread render (`listingFaqs` where `listingId` matches and
   `sourceMessageId in (...)`), then both `message-thread.ejs` (shop) and
   `messages/show.ejs` (seller) render a "Published to FAQ" link on the
   message it came from, pointing at `#faq-<id>` on the listing page (shop) or
   the seller's FAQ management page. Anchor ids (`id="faq-<id>"`) added to both
   FAQ listing views.

6. **The merge fold** — `mergeAnonymousCustomer`:
   - `foldConversations`: reads the anonymous customer's conversations and the
     verified customer's own, and for each anonymous one calls the new pure
     `planConversationFold` (`src/app/core/customers/conversation-fold-plan.ts`,
     mirrors `planConversation`'s shape) — `'move'` re-points `customerId` +
     recomputed `subjectKey` in place when the verified customer holds no
     matching thread; `'absorb'` re-points the moving thread's `messages` onto
     the standing thread's `id` (touching `conversationId` only, so every
     message's own `readAt` survives untouched), deletes the now-empty moving
     row, and reads `lastMessageAt` back as `max(sentAt)` across the standing
     thread's messages — PHP's `absorb()` shape.
   - `repointSentMessages`: `messages.senderId` re-points where
     `senderType = 'customer'` — Rails' `sent_messages`, previously missing in
     Node.
   - `customerBlocks` added to `REPOINTED_CUSTOMER_TABLES` (blind repoint,
     like `orders`/`listingEvents`/`notifications`) — a block follows the
     person. `conversations` removed from that list (it folds instead, see
     above).

7. **The manifest test** —
   `src/app/actions/customers/customer-owned-tables-manifest.test.ts`. Migrates
   a fresh in-memory database, reads every table from `sqlite_master`, and for
   each one reads its columns via `pragma_table_info` looking for one literally
   named `customer_id`. Asserts that set equals
   `REPOINTED_CUSTOMER_TABLES ∪ keys(FOLDED_CUSTOMER_TABLES) ∪
   keys(LEFT_BEHIND_CUSTOMER_TABLES)` (all three now live in
   `repointed-customer-tables.ts`), with no table claimed twice and every
   folded/left-behind entry carrying a non-empty reason. A new table with a
   `customer_id` column fails this test until it is classified.

### The full left-behind / folded list, with reasons

| Table             | Column        | Handling    | Reason                                                                                           |
| ----------------- | ------------- | ----------- | ------------------------------------------------------------------------------------------------ |
| `orders`          | `customer_id` | repointed   | blind — no fold needed, one order belongs to one customer                                        |
| `listing_events`  | `customer_id` | repointed   | blind                                                                                            |
| `notifications`   | `customer_id` | repointed   | blind                                                                                            |
| `customer_blocks` | `customer_id` | repointed   | blind — a block follows the person                                                               |
| `favorites`       | `customer_id` | folded      | deduplicated against the verified customer's own, so a listing favorited on both sides does not  |
|                   |               |             | become two rows                                                                                  |
| `carts`           | `customer_id` | folded      | folded line-by-line, so the verified customer never ends up with two carts                       |
| `conversations`   | `customer_id` | folded      | folded onto the verified customer's existing thread on the same subject, so a subject never ends |
|                   |               |             | up with two threads                                                                              |
| `customer_merges` | `customer_id` | left behind | the trail record of the merge itself; it names the anonymous customer on purpose, so a stale     |
|                   |               |             | cookie resolves forward                                                                          |

Not scanned by the manifest (not a column literally named `customer_id`, so
outside decision 2's schema-driven scope) but fixed by decision 3 anyway, with
its own dedicated tests: `messages.sender_id` (polymorphic, holds a customer id
only when `sender_type = 'customer'`) — now repointed, matching Rails'
`sent_messages`.

### Deviations / things PHP and Rails should probably match

- **`(listing_id, source_message_id)` FAQ-publish uniqueness is new to this
  ticket.** Neither PHP's `listing_faqs` migration nor Rails' schema has this
  index or a duplicate-publish refusal today — a second publish of the same
  message currently creates a duplicate FAQ row in both. Worth a small
  follow-up ticket in each so all three prototypes hold the same invariant;
  left as-is here since the contract only bound Node to build it.
- **The "Published to FAQ" marker is Node-only** for the same reason — PHP and
  Rails' thread views don't show one. Same follow-up.

### Decisions recorded (already in the ticket's worker brief, restated for the trail)

1. `subject_key` shape follows PHP, not Rails' `COALESCE` expression index —
   Node ids are text ULIDs, a plain unique text column composes with Kysely's
   typed builder like every other unique constraint in this schema.
2. The manifest test reads the schema at test time (`pragma_table_info`), not
   the TypeScript types, so it catches a table the types have not been told
   about yet, not just one the manifest itself forgot.
3. Both un-repointed columns (`messages.sender_id`, `customer_blocks.customer_id`)
   are now repointed, matching Rails (sender_id) and PHP (both).
4. The FAQ double-publish refusal is a domain refusal (flash), not a silent
   no-op — the seller sees why nothing changed.

### Concurrent-open race — what the test proves and what it doesn't

`node-sqlite-dialect.ts`'s single `DatabaseSync` connection, wrapped by
Kysely's own `ConnectionMutex` (armed because `SqliteAdapter.
supportsMultipleConnections` is `false`), fully serializes every
`.transaction().execute()` call against one `AppDatabase` — and every
transaction here opens with `begin immediate`, which takes the write lock
before any read runs inside it. Together these mean two `openConversation`
calls against the app's one database handle can never actually interleave
their read and their write: whichever transaction starts first commits in
full before the other's transaction can begin. The `Promise.all` test in
`open-conversation.test.ts` ("two concurrent opens... settle on one thread")
is real and passes, but it exercises the ordinary `planConversation` reuse
path, not the `onConflict`-then-reread branch — there is no reachable TOCTOU
window to force that branch through this app's own database handle.

The unique index and the `onConflict`/reread fallback are still the right
thing to build: they are what PHP (`firstOrCreate`) and Rails (the `COALESCE`
index) lean on for a database with real multi-connection concurrency, and they
are defense-in-depth here against a future change that removes `begin
immediate` or reads outside a transaction. Recorded rather than silently
assumed correct.

### What was cut

Nothing. All six numbered "What to do" items landed, in the order the ticket's
"If it runs away" section prioritized them (subject_key + race test, merge
fold + manifest test, FAQ uniqueness + refusal, Published-to-FAQ marker).

### Verification

`make check` green: 1826 tests (was 1805), 0 failures, coverage 99.50% lines /
97.28% branches / 99.55% functions (was 99.53/97.28/99.55 — the tiny lines dip
is new uncovered branches elsewhere in the file list, still well over the
95/90 gate). `make smoke` 8/8 green (includes "a question on a listing becomes
an answer and then a published FAQ"). `make routes` prints cleanly.
`make docs-check` renders all 21 diagrams, 0 failed.

### Fix-up

Review found two items, both fixed:

1. **`isSameConversationSubject` now IS `subjectKey(a) === subjectKey(b)`**,
   not a separately written field-by-field `&&` chain that happened to agree
   with it. `SUBJECT_KEY_COLUMNS`'s `satisfies Record<Exclude<keyof
   ConversationSubject, 'kind'>, string>` already forces `subjectKey` to visit
   every column on the type; equality now inherits that same guarantee rather
   than needing its own. Reworded both functions' comments to remove the
   circularity this introduced (`subjectKey`'s comment no longer explains
   itself in terms of `isSameConversationSubject`, since the dependency now
   runs the other way). `make check` after the change: still 1826 tests, 0
   failures — every existing equality test passed unchanged, as expected
   (`subjectKey` omits null columns exactly where the old `&&` chain compared
   two nulls as equal).
2. **`docs/identity.md`** — corrected the sentence claiming
   `customer_merges.anonymous_customer_id` is named by the manifest test. The
   manifest only scans columns literally named `customer_id`; only
   `customer_merges.customer_id` is in its scope (left behind, on purpose).
   `anonymous_customer_id` sits outside the scan entirely, the same position
   `messages.sender_id` is in — reworded to say that instead.

Coverage before this fix-up: 1826 tests, 99.50/97.28/99.55. After: 1826 tests,
99.50/97.27/99.55 (branches down 0.01 — `isSameConversationSubject` lost its
own six-way `&&` to cover; still well over the 90% gate).
