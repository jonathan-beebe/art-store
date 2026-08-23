---
id: IMPRV-001
type: improvement
status: resolved
created: 2026-08-23
---

# IMPRV-001: Inbox query cost, thread-page assignment, and doc corrections from the review

## Problem
Review of the landed messaging feature found: (a) the inbox action (`src/app/controllers/concerns/messaging_site.rb:9`) issues three queries per row — a COUNT per `unread_count_for`, and `counterpart_of` reloading a participant per row through `public_send`; 8 threads cost 26 queries. (b) `src/app/views/seller/conversations/show.html.erb:28` calls `ListingFaq.draft_from(@conversation)` from the template where every other page assigns in the controller. (c) `docs/architecture.md`'s testing bullet cites `assert_turbo_stream_broadcasts [conversation, participant], count: 1`, a call the suite does not contain; `docs/messaging.md` says an open inbox page and the badge "cannot disagree", but a broadcast updates the badge and the thread while an open inbox page's rows stay as rendered; support threads open against `Admin.on_duty` from the seller/customer side but against `current_admin` from the admin side, which with a second admin row yields two threads both titled "Art Store support" — undocumented; `docs/ontology.md` has a stray blank line under "Identity and messaging".

## Goal
The inbox costs a constant number of queries, thread-page data is assigned where the architecture doc says, and the docs state what the code does.

## Outcome
The inbox action issues the same number of queries for 1 row as for 20 (verified in a test or a recorded runner probe); the seller thread page's FAQ draft is a controller assignment; the three doc statements read true against the code and the second-admin behaviour is stated; the suite stays at 100% line coverage.

## Why it matters
The inbox is the page every actor opens most; the docs are what the stack comparison reads.

## Discovery notes
`includes(:subject, :seller, :customer, :admin)` removes the participant reloads; the per-row COUNT folds into one grouped query (`Message.unread_for(actor).where(conversation: ids).group(:conversation_id).count`) that the view reads from a hash, or stays per-row through the association preloaded with a filtered `has_many` — the maker picks. `counterpart_of` reloading the signed-in actor itself is the `public_send` on `belongs_to`; preloading covers it. For (b), `MessagingSite#show` is shared — the seller controller can override `present_thread` or assign after calling super.

## Related work
- FEAT-011
- FEAT-013
- FEAT-014

## Working

**(a) Inbox query cost.** Measured with `count_queries`, a new
`IntegrationHelpers` method that subscribes to `sql.active_record` and drops
`SCHEMA`, `TRANSACTION` and cached statements. One seller's inbox, every row a
`listing_question` carrying one unread message:

| rows | before | after |
| --- | --- | --- |
| 1 | 7 | 7 |
| 8 | 21 | 7 |
| 20 | 45 | 7 |

Two queries per extra row before: the `COUNT` behind `unread_count_for` and the
customer `counterpart_of` loaded through `public_send`.
`Conversation.unread_counts_for(actor, conversations)` groups the page's counts
into one query and returns a hash with `default = 0`, which is what the three
inbox views read; `includes(:subject, :seller, :customer, :admin)` covers the
participants. The seven that remain are the session lookups, the layout's
notification and unread-badge counts, the conversations themselves, the
subjects, the customers and the counts — none of them per row.

`Conversation#unread_count_for` stayed and now reads
`self.class.unread_counts_for(actor, [self])[id]`, so the rule has one
definition and the thirteen walks that ask about a single thread keep reading
the way they did. `MessagingSite#index` holds no domain `if` — it assigns two
things and the view reads them.

Proof: `Seller::ConversationsControllerTest` "the inbox costs the same number
of queries whatever it holds" builds 1 row, counts the request, builds 19 more
and counts again.

**(b) Thread-page assignment.** Landed with BUG-002. The seller thread view
reads `@faq`, assigned by `SellerThreadPage#present_thread`, because a refused
publish has to put its own record where the draft would go.

**(c) Docs.**
- `docs/architecture.md`'s testing bullet cited
  `assert_turbo_stream_broadcasts [conversation, participant], count: 1` and
  `assert_no_turbo_stream_broadcasts`; the suite contains neither. It now cites
  `capture_turbo_stream_broadcasts([conversation, seller])` and
  `assert_turbo_stream_broadcasts([seller, :unread_messages], count: 0)`, both
  of which `test/models/conversation_test.rb` and `message_test.rb` run.
- `docs/messaging.md`'s "cannot disagree" now says what holds: the three read
  one scope and answer the same at the moment each runs, and what arrives
  afterwards moves the nav badge and an open thread page while an inbox page
  already rendered keeps the numbers it was drawn with. The unread-counts
  diagram reads `Conversation.unread_counts_for`, and the section states the
  constant query cost.
- `docs/messaging.md` states the second-admin case: the two support buttons open
  against `Admin.on_duty` while the admin site's Message buttons open against
  `current_admin`, so a second `admins` row gives a seller two `admin_seller`
  threads. Both inbox rows read "Art Store support", since `topic` for the
  support kinds is the desk; the counterpart's name is what separates them.
- `docs/ontology.md` lost the stray blank line under "Identity and messaging".

**Left alone.** The second-admin case is documented, not changed — settling it
needs an assignment model, which is out of this prototype's scope.
`docs/identity.md` and `docs/ontology.md` list the merged associations without
naming conversations; true before and after BUG-001.

**Verification.** `make test`: 750 runs, 2362 assertions, 0 failures, 0 errors,
line coverage 1268 / 1268 (100.00%). Two tests added: the inbox query count and
`Conversation.unread_counts_for` over two threads.
