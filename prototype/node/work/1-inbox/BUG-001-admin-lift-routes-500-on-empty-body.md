---
id: BUG-001
type: bug
status: open
created: 2026-08-23
---

# BUG-001: Admin lift routes answer 500 when the request has no body

## Problem
`POST /admin/customers/:id/blocks/lift` and `POST /admin/listings/:id/removals/lift` return 500 for a request with no body. `moderationRoute` in `src/app/sites/admin/routes/moderation.ts` calls `command.form.parse(request.body)` and Fastify leaves `request.body` undefined when no body was sent, so zod throws instead of reporting a validation result. Found during the FEAT-007 curl walk with `curl -X POST`.

## Goal
Every admin write route answers a well-formed 4xx or a redirect for any request shape, never a 500.

## Outcome
A bodiless POST to either lift route (and to the remove / block routes) is handled the same way as a POST with missing fields; an integration test beside the route proves it.

## Why it matters
A 500 on an admin action reads as a crash in a demo, and the same pattern may exist in other routes that parse `request.body` directly.

## Discovery notes
`request.body ?? {}` before parsing is the smallest fix; check the other sites' routes for the same call shape while there.

## Related work
- FEAT-006 (admin site), FEAT-007 (found it)
