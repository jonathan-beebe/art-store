---
id: FEAT-009
type: feature
status: resolved
created: 2026-08-22
---

# FEAT-009: Docs — architecture drift check, identity, orders, escrow, messaging, admin, data model, ontology

## Problem
`docs/architecture.md` was written before any code existed and will have drifted; the brief asks for a docs folder with sequence diagrams, flow charts, and state machines capturing the product, and the two new areas (admin, messaging) have no feature docs.

## Goal
A reader can understand every flow in the product from `docs/` alone, and every diagram renders and names real code.

## Outcome
- `docs/architecture.md` corrected against the code (layout, routes, names, thresholds).
- `docs/identity.md` (sequence: seller sign-in; guest verification with merge; identity resolution flowchart), `docs/orders.md` (checkout sequence, order and fulfillment state diagrams incl. cancel), `docs/escrow.md` (ledger flow, payout sequence, worked example), `docs/messaging.md` (conversation kinds, ask → answer → FAQ sequence, access rule), `docs/admin.md` (moderation effects, page-view rollup), `docs/data-model.md` (ER diagram from the migrations), `docs/ontology.md` (every entity incl. admin, removal, block, conversation, message, FAQ), `docs/README.md` index.
- `make docs-check` extracts every Mermaid block and renders it with `minlag/mermaid-cli`; all pass.

## Why it matters
The showdown compares three stacks; the docs are how a reader who did not build it judges the design.

## Discovery notes
`prototype/rails/docs/*` is the model — same doc set plus two new ones. Follow the `diagramming` skill: one question per diagram, names match the code, prose frames.
- Render check: `docker run --rm -v "$D":/data -v "$D/tmp":/tmp minlag/mermaid-cli -i /data/x.mmd -o /data/x.svg` (bind-mount `/tmp` to avoid ENOSPC). Bare labels `to`, `in`, `links`, `end` break the parser — quote them.

## Related work
- `prototype/rails/work/3-done/FEAT-007-docs-diagrams.md`

## Working

### What drifted in `docs/architecture.md`, and how it was corrected

The doc was written before any code existed. Fifteen things had drifted.

| #   | Drift                                                                | Correction                                                            |
| --- | -------------------------------------------------------------------- | --------------------------------------------------------------------- |
| 1   | Stack table carried `^` ranges and "Node 24.19"                      | Installed versions from `src/package-lock.json`: fastify 5.12.1,      |
|     |                                                                      | @fastify/view 12.0.0, ejs 6.0.1, formbody 9.0.0, cookie 11.1.2,       |
|     |                                                                      | static 10.1.3, multipart 10.1.1, better-sqlite3 13.0.3, kysely        |
|     |                                                                      | 0.29.5, zod 4.4.3, typescript 5.9.3, eslint 9.39.5, typescript-eslint |
|     |                                                                      | 8.67.0, @tailwindcss/cli 4.3.3. Runtime is the image                  |
|     |                                                                      | (`node:24-bookworm-slim`). Added `verbatimModuleSyntax` and the       |
|     |                                                                      | `kysely/migration` import note (FEAT-001's first decision).           |
| 2   | No port anywhere                                                     | The container serves 4000 (`docker-compose.yml`, `config.ts`          |
|     |                                                                      | default).                                                             |
| 3   | `app/plugins/` absent from the layers table and the tree             | Named in Coordination with all six modules — `flash`, `form-body`,    |
|     |                                                                      | `identity`, `page-views`, `site-render`, `unread-messages`. FEAT-001  |
|     |                                                                      | flagged the gap.                                                      |
| 4   | `app/sites/<site>/queries/`, `app/db/commerce-schema.ts`,            | All named. `queries/` described as the site's read side (Kysely, no   |
|     | `app/cli/`, `app/clock.ts`, `app/not-implemented-error.ts` absent    | domain logic); `schema.ts` + `commerce-schema.ts` as the two halves   |
|     |                                                                      | of the row types; `clock.ts` explained (core takes a `Date`, so       |
|     |                                                                      | `Clock` is not core).                                                 |
| 5   | Adapters listed `app/delivery` as holding both delivery ports        | Only `MagicLinkDelivery` lives there with two implementations.        |
|     |                                                                      | `NotificationDelivery` is a port type in `app/core/notifications/`    |
|     |                                                                      | with no implementation, and `ActionContext.notificationDelivery` is   |
|     |                                                                      | never set by `buildApp`. Said so.                                     |
| 6   | Deployables diagram had one process                                  | Added the payout CLI as a second entry point onto the same file, and  |
|     |                                                                      | marked email as an unimplemented port.                                |
| 7   | Sites table had three rows, no plugin names, no guard structure      | Four rows including `authSite`; plugin names (`shopSite`,             |
|     |                                                                      | `sellerSite`, `adminSite`, `authSite`); the encapsulation that        |
|     |                                                                      | carries each guard (`storefrontRoutes`, `adminConsoleRoutes`, the     |
|     |                                                                      | seller's inner guarded plugin) and why sign-in sits outside each.     |
|     |                                                                      | Cookie attributes added.                                              |
| 8   | Identity named `FlashMagicLinkDelivery` / `MailMagicLinkDelivery`    | The code has `flashMagicLinkDelivery` / `mailMagicLinkDelivery`       |
|     | classes                                                              | values plus `selectMagicLinkDelivery`. Added the                      |
|     |                                                                      | `planCustomerIdentity` discriminated union (four variants),           |
|     |                                                                      | `signInRoutes` as a plugin factory with `admits` / `refusal` /        |
|     |                                                                      | `accountView`, the one-use `consumed_at is null` UPDATE, and that a   |
|     |                                                                      | cookie alone is not signed in.                                        |
| 9   | `customerStanding` placed at                                         | It is `app/core/moderation/customer-standing.ts` (FEAT-003 decision — |
|     | `app/core/customers/customer-standing.ts`                            | it belongs with `activeRemoval`). Added `activeBlock`,                |
|     |                                                                      | one-active-per-subject, and that a block also stops paying and        |
|     |                                                                      | messaging.                                                            |
| 10  | Order state diagram labelled `pending_verification → cancelled`      | There is no stale sweep. Every edge now matches                       |
|     | "customer cancels or stale sweep"                                    | `ORDER_STATUS_TRANSITIONS` exactly, including `payment_failed →       |
|     |                                                                      | payment_failed` and terminal `cancelled`.                             |
| 11  | ER diagram missing `cart_items` and the FAQ-from-message edge; table | Added both; said 23 tables in nine migrations and which four the      |
|     | count implied 15                                                     | overview leaves to `data-model.md`.                                   |
| 12  | Messaging "Opened from" column named pages, not routes               | Replaced with the real paths. Added `planConversation`,               |
|     |                                                                      | `conversationPath`, `read_at`-per-message and why, FAQ rows existing  |
|     |                                                                      | only while published, the first-admin-by-id support counterpart,      |
|     |                                                                      | `MESSAGE_BODY_MAX_LENGTH`.                                            |
| 13  | Analytics said "an `onResponse` hook upserts" with no names          | `addPageViewRollup` (root), `isCountablePageView`, `pageViewSite` off |
|     |                                                                      | `request.routeOptions.url`, `recordPageView`, and the unique index    |
|     |                                                                      | that makes it one statement.                                          |
| 14  | Testing claimed `app/test/smoke.test.ts` "walks the whole product in | It held three cross-site tests and no money walk. FEAT-010 added the  |
|     | one test" (checkout, payout, moderation)                             | missing ones mid-ticket; the doc now lists the six that exist (three  |
|     |                                                                      | sites off one stylesheet, sign-in to weekly payout, question → FAQ,   |
|     |                                                                      | removal leaves the storefront, block refuses checkout, admin messages |
|     |                                                                      | a seller) and names `app/actions/orders/order-lifecycle.test.ts` over |
|     |                                                                      | `app/test/commerce-world.ts` as the action-level walk. Added a        |
|     |                                                                      | command table (`npm test` / `coverage` / `typecheck` / `lint` /       |
|     |                                                                      | `check`), the real helper names, and the per-site fixtures.           |
| 15  | Repository layout and the skills-mapping table were both speculative | Layout rewritten against the tree. `npm test -- <path>` does not      |
|     |                                                                      | scope `node --test`; corrected to `node --test <file>`, and           |
|     |                                                                      | `buildApp` → `buildTestApp` for integration tests.                    |

### Docs written

| Doc                    | Diagrams                                                                                          |
| ---------------------- | ------------------------------------------------------------------------------------------------- |
| `docs/README.md`       | index, no diagram                                                                                 |
| `docs/architecture.md` | 4 — deployables, layers, commerce ER, order status                                                |
| `docs/identity.md`     | 3 — seller sign-in sequence, guest verification with merge-as-fold, identity-resolution flowchart |
| `docs/orders.md`       | 3 — checkout sequence (guest vs verified), order status, fulfillment status                       |
| `docs/escrow.md`       | 2 — ledger flow, payout-run sequence (plus a worked $100 example)                                 |
| `docs/messaging.md`    | 3 — question → reply → published FAQ sequence, access-rule flowchart, unread-count flow           |
| `docs/admin.md`        | 2 — moderation effects, page-view rollup sequence                                                 |
| `docs/data-model.md`   | 1 — full ER from the migrations, 23 tables with columns and caveats                               |
| `docs/ontology.md`     | 1 — concept-level value flow, then every entity                                                   |

`docs/review.md` belongs to FEAT-010; the index links it and this ticket did
not touch it.

### `make docs-check`

`docker/docs-check.sh` extracts every ```` ```mermaid ```` block under `docs/`
into a scratch directory (one `<doc>-NN.mmd` per block) and renders each with
`docker run --rm -v "$D":/data -v "$D/tmp":/tmp minlag/mermaid-cli`. `/tmp` is
bound to a host directory because mermaid-cli's Chromium profile overflows the
image's own; the work directory is `chmod 777` because the image runs as its
own user. `DOCS_CHECK_DIR` overrides the scratch location, `MERMAID_IMAGE` the
image. Nothing but Docker is needed on the host.

**Validated: 19 diagrams extracted, 19 rendered, 0 failed.**

### Verified

- Every backticked identifier in the nine docs was greppable in `src/app`
  (384 checked). The five that are not are config keys outside `src/app`
  (`complexity`, `erasableSyntaxOnly`, `verbatimModuleSyntax`), the
  `TRANSITIONS` naming convention, and `tsx`, which is named as a thing the
  project does not use.
- Every route path in the docs matches a `.get(...)` / `.post(...)` in
  `src/app/sites/**`. `/sellers-guide` is the one exception: it is a
  hypothetical from `pageViewSite`'s own comment.
- Every table and column in `docs/data-model.md` was read off the nine
  migration files, including the check constraints, the unique indexes, and
  `messages.sender_id` having no foreign key.

### Found in the code, left as-is

- `NotificationDelivery` has no implementation and `buildApp` never sets
  `ActionContext.notificationDelivery`, so the port is dead wiring today. The
  docs say so rather than describing an in-app implementation that does not
  exist.
- BUG-001 (admin lift routes 500 on a bodiless POST) was fixed by a parallel
  agent mid-ticket; `docs/admin.md` describes the `formBody` shape that landed.
- FEAT-010 moved `sellerListingTransitions`
  (`app/sites/seller/listing-transitions.ts`) into the core as
  `availableListingTransitions` (`app/core/listings/listing-status.ts`) and
  filled out `app/test/smoke.test.ts` while this ticket was open. Both docs
  follow the code as it now stands; anything FEAT-010 lands after this commit
  needs a re-read of `docs/architecture.md` and `docs/admin.md`.

### Not committed

`work/journal.md` carries BUG-001's and FEAT-010's lines alongside this
ticket's, so it is left for the orchestrator rather than committed here.
