---
id: FEAT-063
type: feature
status: resolved
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

## Working
`AnalyticsEventName` gains `HelpAnswered`/`HelpUnanswered` (labels "Marked
helpful"/"Marked not helpful", verbs, a check-circle/question-mark-circle
icon pair) — automatic on the admin analytics entry page's events table and
every event-filter segmented control, which already iterate every case.
`AnalyticsEvent::forHelpArticle()` shapes the row like `forStore()`:
`subject_type = 'help_article'`, `subject_id` the article slug.
`App\Domain\Seller\HelpArticleFeedbackCollapse::dedupeKey()` windows on
the UTC day and folds the event name in, so a Yes and a later No the same
day each get their own row.

One invokable controller (`HelpArticleFeedbackController`), the route's
own `outcome` default naming which case a submission records — same
404-on-unknown-slug shape as `HelpArticleController`. Yes redirects back
to the same article with a flash ("Thanks — glad it helped."); No
redirects to the create-conversation form. The article page's two links
became two POST forms with `@csrf`, no JS. Both routes sit under the
existing `auth.seller` group.

The event page's own breakdown for these two names is a new
`EventBreakdown::Article` case — one row per article slug, allowed and
defaulted for `help.*` the way `page.view` defaults to `Pattern`, read
through a `subjectTotals()` helper `listingRows()` and `articleRows()`
both call.

### Review pass

`actor_id` stayed null: `AnalyticsEvent::forHelpArticle()` no longer
writes the seller's id there — every `App\Analytics\Admin` actor reader
(`ActorAggregates`, `EventDetail`'s actor breakdown) resolves `actor_id`
against `customers`, and a seller is never one, so a seller id there was
landing rows the actors page could not open (a 404 on the `sel_` id) and
inflating the actor list with reads that were never customers.
`data.seller_id` carries the seller instead. `EntityActivity`'s
`help_article` feed-row branch (an actor page's row naming an article by
slug, unlinked) was already unreachable in practice — a customer's own
feed scopes by `actor_id`, and a help event's is now always null — so it
came out along with its tests rather than staying as dead code; the id
chip its row would have shown for a slug (`entities/show.blade.php`) is
moot with it.

The two POST controllers collapsed into `HelpArticleFeedbackController`
above once both needed only to differ by which `AnalyticsEventName` case
they record.

`docker compose run --rm app php vendor/bin/pest` green for every touched
sidecar (AnalyticsEventNameTest, AnalyticsEventTest,
HelpArticleFeedbackCollapseTest, HelpArticleFeedbackControllerTest,
HelpArticleControllerTest, AnalyticsControllerTest, ActorControllerTest,
EventBreakdownTest, EventDetailTest, EventControllerTest) and `pint --test`
clean. `make check` intentionally skipped — the orchestrator runs one gate
on the merged branch; `make precommit` runs on commit via the hook.
