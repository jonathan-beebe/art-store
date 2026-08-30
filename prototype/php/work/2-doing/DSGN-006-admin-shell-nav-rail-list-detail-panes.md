---
id: DSGN-006
type: design
status: open
created: 2026-08-30
---

# DSGN-006: The admin shell is a nav rail with list and detail panes

## Problem

Every admin section is a page that replaces the whole screen. Opening an
order from the orders list loses the list; going back reloads it and
loses your place. On a wide screen the nav spends a full row across the
top while the content below it sits in a 1152px column, so the width a
desktop has is spent on chrome and margins rather than on the two things
a founder reads together — the list and the item.

## Goal

A founder reads a list and an item at the same time, and moves between
items without losing the list.

## Outcome

At `xl` and up the admin renders as panes: the twelve sections in a
fixed-width rail down the left with the current section marked, and the
content to its right. Each pane scrolls on its own; the page does not.

Sellers, customers, listings, orders, fulfillments, and messages render
a list pane beside a detail pane: opening an item fills the detail pane
and leaves the list in place with that item marked, and a section with
no item chosen shows the list beside a prompt. Their list rows carry the
same facts their tables carry today, in cells of two lines.

Dashboard, accounting, ledger, payouts, site stats, and logs render one
content pane across the full remaining width — no list pane. Logs keeps
today's full-width filterable table, and opening a request still fills
the width.

Existing URLs still address the same things, and below `xl` every page
behaves exactly as it does today.

## Why it matters

The admin's work is comparative — which order, which of these
fulfillments, is this the customer who wrote in. A layout that shows one
thing at a time makes the founder hold the other in their head.

## Discovery notes

Approved design canvas (three panes, full-content, and the cell
hierarchy): https://claude.ai/code/artifact/33dca1ab-3109-4818-a27c-155707807be1
Reference, not a pixel spec. Proportions drawn at 1440: rail 208px, list
400px, detail the remainder.

Advisory:

- The human decided the per-section split recorded in Outcome. Messages
  already has an inbox-and-thread shape, which is this pattern by
  another name.
- The natural URL mapping needs no new routes: an index route renders
  the list with the detail pane empty; a show route renders the same
  list with that item in the detail pane. Worth confirming early, since
  it decides how much the controllers change.
- The cell hierarchy the canvas draws: line one is identity (the
  human-readable name, and when, right-aligned), line two is state (a
  status badge, one supporting fact, and the number that matters,
  right-aligned). Ids do not lead a cell — they belong in the detail
  pane. Anonymous customers have no name, so the id takes the
  supporting slot. Logs are the honest exception: the request line is
  the identity.
- Below `xl` the Menu disclosure and the card lists DSGN-005 shipped
  already are the mobile answer; this ticket is the `xl`-and-up shell.
- The layout component's `full-width` prop and the `xl` breakpoint both
  exist already — this likely reshapes that component rather than
  adding a second one.

## Related work

- DSGN-005 — the admin is small-screen first (the Menu disclosure, the
  card lists, the `xl` breakpoint this builds on)
- DSGN-004 — log viewer redesign (logs keep their full-width table)
- `prototype/php/docs/admin.md` — the admin page table
