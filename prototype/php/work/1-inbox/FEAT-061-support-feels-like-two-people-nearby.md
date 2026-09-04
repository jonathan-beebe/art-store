---
id: FEAT-061
type: feature
status: open
created: 2026-09-03
---

# FEAT-061: Support feels like two people nearby

## Problem
Support in the seller portal is one form (`resources/views/seller/support/create.blade.php`) that opens a thread with the desk. There is no help to read, no contact way other than the form, and nothing that says who answers or when.

## Goal
A seller with a question finds the answer or the person in one place, and knows how soon they will hear back.

## Outcome
- Support opens on the desk: the two people who answer (name, role, presence), the reply-time promise, a Start a conversation button that opens the existing new-conversation form, and the seller's own last reply time.
- Other ways to reach us: email, phone with hours, and a way to book a short call, each drawn from configuration, not hardcoded in the view.
- Help articles grouped by topic (getting paid, shipping, listings, messages), each opening an article page with a "did this answer it?" pair whose No leads to the conversation form; articles are markdown files in the repository with front matter, and an unknown article answers 404.
- The seller's own support threads list with status and open in Messages on the Support tab.
- Four articles ship with real copy on the four topics the portal already documents (escrow and payouts, printing a label and shipping, what a listing needs to publish, turning a question into an FAQ). `make precommit` green; `make check` green before the PR.

## Why it matters
The brief: "Make it feel like we are close." A page that shows who answers, promises a time, and answers the common questions before they are asked is the difference between a form and a desk.

## Discovery notes
- Articles as `resources/help/seller/*.md` with front matter (`group`, `title`, `slug`, `position`); a small `App\Seller\HelpArticles` reader with a parsed cache; `league/commonmark` may already be in `composer.json` (check) — otherwise a minimal markdown subset is fine.
- Desk facts (names, hours, email, phone, booking URL) in `config/support.php` read from env with `[PLACEHOLDER]`-safe defaults; the admin seeder's two admins are the desk.
- Presence can be static configuration in this cut (hours-based), no realtime.
- The existing `SupportController@create/store` stays the conversation form; the new page is the index.

## Related work
- PR #62 (messaging v2 — the desk), FEAT-050
- Design canvas: https://claude.ai/code/artifact/9f8ad3b7-a73e-45b9-873e-fd704193acad (Support)
