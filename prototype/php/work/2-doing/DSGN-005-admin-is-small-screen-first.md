---
id: DSGN-005
type: design
status: open
created: 2026-08-30
---

# DSGN-005: The admin is small-screen first

## Problem

Every admin page is built for a wide viewport. The layout component's
`max-w-6xl` column, the header's flat nav (twelve links that wrap three
deep on a phone), and the desktop tables and grids do not reflow: on a
390px screen the logs list's fixed grid tracks collide (status overlaps
the request column), rows run off the right edge, and the page reads as a
narrow box inside a wider one. The admin is the founders' operational
tool and it is unusable on the device they carry.

## Goal

Every admin page works on a phone as well as it does on a desktop.

## Outcome

On a 390px viewport: the admin's nav is reachable without wrapping, every
page's content spans the full width, no row or table overflows the
viewport horizontally, and every control meets a 44px touch target. The
dashboard reads as a drill-down hub whose status counts lead into their
filtered lists; list pages render one card per row; detail pages open
with a back link naming the list they came from. On the logs page, a
phone user opens a request's story as a full-screen view while a desktop
user keeps the in-place expansion. Desktop layouts at `sm` and up are
unchanged.

## Why it matters

Accessibility is a project principle, and a layout that requires a wide
viewport excludes the phone. The admin is where a founder answers "did
that order pay" and "what broke" — questions that arrive away from a
desk.

## Discovery notes

Approved design canvas (seven phone artboards: dashboard, list, detail,
shell menu, logs list, and two options for drilling into a request):
https://claude.ai/code/artifact/ca6ed811-d91f-471d-9205-806cc69f8549
It carries the intended anatomy and two sticky notes stating the rules it
applies. Treat it as the reference, not a pixel spec.

Advisory, from the canvas:

- JS-off is absolute, so the nav menu is a `<details>` disclosure, not a
  drawer. The logs page already ships a JS-free popover for More filters —
  the same pattern.
- The logs drill-down was drawn twice on purpose. Option A pushes the
  story over the list as a sheet with its own back link; Option B is
  today's in-place expansion. The recommendation is A on phones and B at
  `sm` and up — the story route already exists, so a phone row can simply
  link to it while wider viewports keep the disclosure. Both are drawn
  with their tradeoffs noted.
- The layout component already grew a `:full-width` opt-out for the logs
  pages (DSGN-004); the wider change is which pages opt out and how the
  container behaves below `sm`.
- Sweep every admin page, not only the ones drawn: sellers, customers,
  listings, orders, fulfillments, accounting, ledger, payouts, stats,
  messages, and the logs story view.

## Related work

- DSGN-004 — log viewer redesign (the desktop layout this extends)
- `prototype/php/docs/admin.md` — the admin page table
- `docs/principles.md` — accessibility as a project principle
