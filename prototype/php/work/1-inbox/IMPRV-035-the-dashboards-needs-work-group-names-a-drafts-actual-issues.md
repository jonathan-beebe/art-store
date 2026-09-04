---
id: IMPRV-035
type: improvement
status: open
created: 2026-09-04
---

# IMPRV-035: The dashboard's "needs work" group names a draft's actual issues

## Problem
The dashboard's "Listings that need work" group lists every draft and every sold-out listing rather than only the drafts a publish issue actually blocks, because `Listing::publishIssues()` costs five or more queries per listing (`app/Models/Listing.php:262` — reads `optionAxes` with counts, `variants`, a `CategoryProperty` query, and `listingAttributes`, per call) and the group can show up to five rows on the portal's most-visited page (audit `__local__/design/seller-portal/AUDIT.md` §6, FEAT-055 row).

## Goal
A seller reads why a draft is in the "needs work" group without leaving the dashboard, at a query cost the portal's busiest page can afford.

## Outcome
The "needs work" panel's draft rows say what blocks each draft from publishing, not only that the listing is a draft, and the dashboard's existing query-count test still holds the page to a fixed number of queries regardless of how many drafts a seller has.

## Why it matters
The panel's own copy ("Draft · not on the storefront yet") tells a seller nothing they didn't already know from the Status column; the whole point of a focus group is to save the click to find out what is wrong.

## Discovery notes
- `App\Seller\NeedsAttention` and `App\Domain\Seller\AttentionQueue` build the panel today; `DashboardControllerTest.php:426`'s query-count guard is the N-invariance test any change here must keep passing.
- Batching the checks that read `optionAxes`/`variants`/`CategoryProperty`/`listingAttributes` across several listings in a handful of grouped queries, rather than one call per row, is one direction; the seller's own publish panel (`ListingStatusController`) already calls `publishIssues()` per listing, one click away, and is unaffected either way.

## Related work
- FEAT-025 (`ConfiguratorPublishValidation`, `publishIssues()`)
- FEAT-055 (found and left this behind)
