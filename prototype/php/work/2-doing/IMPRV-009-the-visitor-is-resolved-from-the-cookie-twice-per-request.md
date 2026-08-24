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

## Working

### The change

`CustomerIdentity::fromCookie(Request, ResolveCustomerFromCookie)` is the one
door both middlewares now go through. It reads the cookie, runs the action
the first time a request asks, writes the answer into
`$request->attributes` under `customer.from_cookie`, and returns what is
stored from then on. `NameRequestVisitor::nameActor()` and
`ResolveCustomerIdentity::handle()` each call it; neither knows nor cares
which of them asked first.

The memo sits beside the request attribute `CustomerIdentity::current()`
already reads and the cookie the same class already owns, so the action
itself stays a question about a cookie value with no HTTP knowledge.

**"Resolved to nothing" is a stored `null`.** `ParameterBag::has()` is
`array_key_exists`, so an attribute holding `null` is distinguishable from an
attribute that was never set. A cookie naming a customer no row carries costs
one `customer_merges` lookup and one `customers` find for the request, not one
pair per asker. `ResolveCustomerIdentity` on a route where `NameRequestVisitor`
never ran finds no attribute and resolves for itself.

Files:
- `src/app/Support/CustomerIdentity.php` — `fromCookie()`, `RESOLVED_ATTRIBUTE`
- `src/app/Http/Middleware/NameRequestVisitor.php` — calls it
- `src/app/Http/Middleware/ResolveCustomerIdentity.php` — calls it
- `src/app/Http/Middleware/ResolveCustomerIdentityTest.php` — query-count tests
- `src/app/Support/CustomerIdentityTest.php` — the memo answers a second ask
  after the row is deleted

### M7, returning visitor

Both columns measured 2026-08-24 against the same seeded database, the
`after` column with the change applied and the `before` column with it
stashed, so the two are the same data.

| page | before | after | ticket target |
|---|---|---|---|
| `/` | 16 | **14** | 14 |
| `/cart` | 13 | **11** | 11 |
| `/art/{slug}` | 17 | **15** | 16 |
| `/favorites` | 13 | **11** | 11 |

`/art/{slug}` measured 17 rather than the 18 RSRCH-001 recorded. The
unchanged code measures 17 on today's database, which holds 3196 customers
and weeks of page views against the freshly seeded database RSRCH-001 read;
the extra query belongs to that difference in state. Against one database the
drop is two queries on every page.

### The log payload

`GET /` against the container on port 8000, a returning visitor's cookie in a
curl jar. Before:

```
{"ts":"2026-08-24T21:36:07.815Z","level":"info","event":"http.request","phase":"did","msg":"GET / 200","request_id":"req_01M0TV7G3JYWTZ9PV3GYK49J14","session_id":"ses_01M0TV7FVPQNS24C457ZWD2EX3","actor_type":"customer","actor_id":"cus_01M0TV7FWZ2187JEASKSX5KQAH","data":{"status":200},"duration_ms":21}
```

After:

```
{"ts":"2026-08-24T21:40:47.174Z","level":"info","event":"http.request","phase":"did","msg":"GET / 200","request_id":"req_01M0TVG0WN7CPTCW01KJ2P1EVB","session_id":"ses_01M0TVG0R3B7D2WB0CTT2F4RHD","actor_type":"customer","actor_id":"cus_01M0TVG0RMEW1TZCZ494NQ985R","data":{"status":200},"duration_ms":48}
```

Same keys, same order, `actor_type` `customer`, `actor_id` a `cus_…`. The ids
differ because each capture used its own jar.

### A first-time visitor still gets a row

```
$ curl -s -o /dev/null -c /tmp/j http://localhost:8000/
customers before: 3196   after: 3197
Set-Cookie: customer_id=eyJpdiI6InMzRG1rZGR…
```

### The gate

`make check`: Pint 615 files, PHPStan level max `[OK] No errors`, **1831
tests, 4955 assertions, coverage 100.0 %**. Four tests added: the two dataset
cases of the once-per-request query count, the resolve on a route nothing
else named a visitor for, and the memo read in `CustomerIdentityTest`.

### Left out

`MagicLinkVerificationController` resolves the cookie for itself
(`$resolveFromCookie(CustomerIdentity::cookieValue($request))`) on
`GET /auth/magic/{token}`, a `web` route where `NameRequestVisitor` already
asked. Routing it through `fromCookie()` would save the same two queries
there. It is one request per sign-in rather than one per page view, and the
controller merges customers a few lines later, so sharing a memo across that
boundary wants its own reading. Not filed.
