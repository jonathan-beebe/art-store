---
id: IMPRV-009
type: improvement
status: open
created: 2026-08-24
---

# IMPRV-009: The visitor is resolved from the cookie twice per request

## Problem
`ResolveCustomerFromCookie` runs twice on every storefront request.
`NameRequestVisitor` calls it to put `actor_id` on the request's log lines,
and `ResolveCustomerIdentity` calls it again to bind the visitor the
controllers read. Each call is two queries — the `customer_merges` lookup
and the `Customer::find` behind it — so four queries answer one question,
and neither middleware knows the other asked it.

Measured (RSRCH-001 M7), returning visitor:

| page | queries |
|---|---|
| `/` | 16 |
| `/cart` | 13 |
| `/art/{slug}` | 18 |
| `/favorites` | 13 |

Two of each of those counts are the duplicate. The SQL for `GET /`, in order,
with the pair repeated at 4 and 5:

```
 1 select * from "sessions" where "id" = ? limit 1
 2 select "customer_id" from "customer_merges" where "anonymous_customer_id" = ? limit 1
 3 select * from "customers" where "customers"."id" = ? limit 1
 4 select "customer_id" from "customer_merges" where "anonymous_customer_id" = ? limit 1
 5 select * from "customers" where "customers"."id" = ? limit 1
 6 select distinct "medium" from "listings" ...
```

## Goal
A request resolves its visitor once, and both readers get that answer.

## Outcome
RSRCH-001 M7 drops by two on every storefront page: `/` from 16 to **14**,
`/cart` from 13 to **11**, `/art/{slug}` from 18 to **16**, `/favorites`
from 13 to **11**. The log line for a storefront request still carries the
same `actor_type` and `actor_id` it carries today, and a request whose
cookie names a merged-away customer still reaches the customer it was merged
into.

## Why it matters
It is the one place where the per-request query count can come down without
touching a page, a query, or a schema — the two callers are asking the same
question of the same cookie in the same request.

## Discovery notes
`NameRequestVisitor` runs first (appended to the `web` group);
`ResolveCustomerIdentity` is a route-alias middleware and runs after it. So
the first to resolve can leave its answer where the second finds it.

Memoise on the request rather than in the action or the container: the
request is the scope the answer is true for, and
`LogRequestStory::REQUEST_ID_ATTRIBUTE` already establishes
`$request->attributes` as where this application parks per-request facts.
A resolve that found nothing has to be distinguishable from a resolve that
has not happened — a cookie naming a customer that does not exist must not
re-query on the second call.

Watch the ordering assumption: `ResolveCustomerIdentity` is aliased and
could be used on a route outside the `web` group, where nothing populated
the attribute. It must still resolve for itself when the attribute is
absent, and it must still be able to create a first-time visitor
(`Customer::create([])`) when there is no cookie at all.

Both middlewares carry sidecar tests that assert their query behaviour
directly; the ticket is done when those tests say the resolve happened once.

## Related work
- FEAT-002 (magic-link identity), FEAT-019 (structured logs, `NameRequestVisitor`)
- IMPRV-005 (customer merge)
- RSRCH-001 (M7)
