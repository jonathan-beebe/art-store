---
id: IMPRV-001
type: improvement
status: open
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
