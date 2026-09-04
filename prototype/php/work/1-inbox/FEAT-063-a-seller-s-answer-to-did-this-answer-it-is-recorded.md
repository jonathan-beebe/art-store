---
id: FEAT-063
type: feature
status: open
created: 2026-09-04
---

# FEAT-063: A seller's answer to "did this answer it?" is recorded

## Problem
The help article page (FEAT-061) ends with "Did this answer it?" and a Yes / No pair; No opens the conversation form and Yes does nothing. The desk cannot tell which articles work.

## Goal
The desk can see, per article, how often it answered the question.

## Outcome
- Pressing Yes records a `help.answered` analytics event with the article slug as its subject and the seller as its actor, thanks the seller in place, and records nothing twice for the same seller and article within a day (a dedupe key like `listing.view`'s).
- Pressing No records `help.unanswered` with the same shape before it opens the conversation form.
- `AnalyticsEventName` gains both names with labels, verbs, and icons; the admin analytics event list shows them; an actor's feed names the article.
- `make precommit` green; `make check` green before the PR.

## Why it matters
A control that records nothing trains sellers to ignore it; the desk writes better articles when it knows which ones fail.

## Discovery notes
- `__local__/design/seller-portal/DECISIONS.md` decision 13 ("yes, record it"). The store view recorder (`AnalyticsEvent::forStore()`, `StoreViewCollapse`) is the template for a new subject type.

## Related work
- FEAT-061, FEAT-058, FEAT-039
