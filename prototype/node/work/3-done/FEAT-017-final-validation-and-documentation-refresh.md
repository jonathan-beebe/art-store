---
id: FEAT-017
type: feature
status: resolved
created: 2026-08-23
---

# FEAT-017: Final validation and documentation refresh

## Problem
This ticket absorbs no findings of its own. Its problem is downstream of every other ticket in this manifest: each one changes behavior or adds a component — a `node:sqlite` dialect, a health endpoint, an outbox, SSE, a production image, CI, structured logging, validation at the boundary — that `docs/architecture.md`, `docs/review.md`, `README.md`, and `docs/data-model.md` currently describe under the pre-refinement shape. `docs/review.md`'s known-gaps list names gaps that other tickets in this batch close (the outbox gap FEAT-015 closes is one instance). Once those tickets land, the docs are stale until this one runs, and a reviewer following the README from a clean checkout would hit claims that no longer match the code.

## Goal
A reviewer can follow the README from an empty checkout to a working demo, and every claim the docs make about the system matches the code.

## Outcome
- A reviewer can run `make up`, `make test`, `make coverage`, `make image`, `make routes`, `make outbox` from the README.
- Every claim in the docs is true of the code.

## Why it matters
A reviewer's confidence in every other ticket in this batch depends on the docs and a clean run actually matching the code. A correct system with a stale README reads as an unfinished one; the gap between what was built and what is claimed is exactly what a careful reviewer checks first.

## Discovery notes
This depends on the rest of the manifest — it should be the last ticket worked, after every other ticket in this batch (BUG-002 through BUG-006, FEAT-011 through FEAT-016, IMPRV-001 through IMPRV-008, RFCTR-001 through RFCTR-004) has landed. Starting it earlier means documenting a system that is still changing under it.

Validation steps to run once everything else has landed: `docs/architecture.md`, `docs/review.md`, `README.md`, and `docs/data-model.md` need to describe the refined system accurately — the `node:sqlite` dialect, the health endpoint, the outbox, SSE, the production image, CI, structured logging, and validation at the boundary. The known-gaps list needs rewriting to drop closed gaps and state any that remain. `make docs-check` needs to pass green. A clean first run from an empty tree needs to succeed. A curl walk needs to confirm no route 500s. The smoke test needs extending to cover `/health` and the outbox.

## Related work
Depends on and follows every other ticket in this manifest: BUG-002, BUG-003, BUG-004, BUG-005, BUG-006, FEAT-011, FEAT-012, FEAT-013, FEAT-014, FEAT-015, FEAT-016, IMPRV-001, IMPRV-002, IMPRV-003, IMPRV-004, IMPRV-005, IMPRV-006, IMPRV-007, IMPRV-008, RFCTR-001, RFCTR-002, RFCTR-003, RFCTR-004.

## Working

Worked last, with every other ticket in the manifest landed. Two halves: close
the follow-ups the other 23 tickets left behind, then verify every claim the
docs make against the code and against a clean run.

### Follow-ups closed

1. **CI ran the suite twice.** `.github/workflows/node.yml` ran `npm run check`
   (which ends in the coverage-gated suite) and then `npm run test:ci` (the same
   gated suite plus an lcov reporter). Took the first of the two options the
   ticket offered: the workflow now runs `npm run typecheck`, `npm run lint`,
   and `npm run test:ci` as three steps, so the suite runs once and a failure
   names itself in the job list. `npm run check` is unchanged and stays the
   local gate; `package.json` is untouched. README's CI section and
   `docs/architecture.md`'s Testing table both say so.

2. **Docs against code.** Every doc rewritten where it was wrong — details
   below.

3. **`docs/review.md`.** Verification section replaced with this run's numbers;
   known-gaps list rewritten from 7 entries to 10 (see below).

4. **Literals for now-Generated columns.** Dropped two of the three:
   `create-listing.ts` no longer passes `status: 'draft'` and `place-order.ts`
   no longer passes `status: 'awaiting_shipment' as const` — both columns
   default to exactly that value in their `create` migrations, and Kysely's
   `Generated<T>` makes them optional on insert, so the removal typechecks with
   no cast. Each site keeps a one-line comment naming the status the row is born
   with, since the code no longer says it. **`record-page-view.ts` keeps
   `count: 1` deliberately**: the column defaults to `0`, so dropping the
   literal would make the first hit of a day count zero. That is not a leftover
   literal, it is the value.

5. **`make docs-check`.** Green, 21 diagrams, 0 failed — including the two new
   Mermaid blocks in `architecture.md`: a flowchart of the path a request takes
   (security headers → cookies → identity → site plugin → guard → route schema →
   handler → core/action → render, with the error-page and not-found branches and
   the `onResponse` rollup), and a sequence diagram of the outbox.

### Docs, claim by claim

Verified each against the code before rewriting; `grep` over every backticked
`app/**` path in every doc now resolves to a file that exists.

- **`architecture.md`** — largest rewrite. Stack table: added a Logging row
  (pino 10.3.1, `app/logging.ts`), stated the dependency counts (10 runtime,
  7 dev, 260 resolved) and that nothing compiles. Deployables diagram: the dead
  "Email delivery (port, unimplemented)" node replaced by the outbox drain
  writing `.eml` files, plus `/health` and `<prefix>/events` on the node.
  Layers: `app/http/` added to Coordination, `app/delivery/` now lists both
  ports and `DeliveryContext`, Core lists `health/`, Entry lists `logging.ts`.
  Deleted the paragraph about `app/not-implemented-error.ts` (the file is
  gone). **New section "The path a request takes"** with the request diagram and
  a table of all ten plugins and what each adds. Sites: `unreadEventsRoute` and
  the per-site `setNotFoundHandler` named, plus the storefront-404-renders-
  signed-out wart stated as a wart. Identity: `mailMagicLinkDelivery` /
  `NotImplementedError` replaced by `outboxMagicLinkDelivery`, delivery is
  `flash | outbox`. Table count "Twenty-three tables in nine migrations" →
  twenty-four across ten of eleven migrations, plus the CHECK-constraint
  paragraph and the **`make fresh`** note IMPRV-004 needs. Listing section:
  `listingDraftErrors` → `parseListingDraft` returning a result union, and the
  magic-bytes extension. Notifications: `NotificationDelivery` moved to
  `app/delivery/`, `notify` defaults to the outbox, and there is no
  `NOTIFICATION_DELIVERY` env var — `ActionContext.notificationDelivery` is the
  seam. **New "Outbox" section.** Orders: `planOrderPlacement` and the
  refusal-not-throw shape. Escrow: `planWeeklyPayout`. Messaging: the SSE badge
  paragraph. **New "Readiness, shutdown, and logs" section** (health checks,
  draining, the logger and its redactions, the thirteen business events).
  Testing table: thresholds 90/80 → **95/90**, `test:ci` and `routes` added,
  `check` described as typecheck → lint → coverage, and what CI runs instead.
  Repository layout rewritten (no `not-implemented-error.ts`, adds `logging.ts`,
  `core/health`, `actions/outbox`, `db/count.ts`, the four CLIs, the four
  partials, `public/app.js`, `storage/outbox/`).
- **`identity.md`** — the `mailMagicLinkDelivery` throws-`NotImplementedError`
  paragraph replaced with the two live deliveries and what each does; the
  sign-in sequence diagram now shows both transaction boundaries (BUG-004's
  consume-and-claim, and the outbox row written with the link).
- **`ontology.md`** — Money entity rewritten for the branded `Cents`
  (`type Cents = number` was three refactors stale); `faqDraftErrors` →
  `parseFaqDraft`; `addPageViewRollup` → the `pageViewRollup` plugin;
  `NotificationDelivery` "a port with no live implementation" corrected; **new
  Outbox message entity**.
- **`messaging.md`** — `faqDraftErrors` → `parseFaqDraft` in the diagram and the
  prose, with the real constant names for the two caps.
- **`orders.md`** — `isCheckoutComplete` gone from the diagram, `parseFaqDraft`-
  style result unions described, `planOrderPlacement` added to the sequence, and
  a paragraph on the stale-cart refusal BUG-003 fixed and the transaction
  BUG-005 closed.
- **`admin.md`** — `addPageViewRollup` → `pageViewRollup`; the `/admin/outbox`
  and `/admin/events` rows added to the Pages table; the `optionalFilter`
  paragraph (IMPRV-002's empty-string fix); **new "The outbox as the platform's
  mailbox" section**.
- **`data-model.md`** — "Twenty-three tables, created by the nine migrations" →
  twenty-four across ten of eleven; CHECK constraints and the `make fresh` note;
  `schema-fidelity.test.ts` named; **`outbox_messages` added to the ER diagram**
  and to the caveats.
- **`escrow.md`** — `planWeeklyPayout` added to the payout sequence with the
  `settledSellerIds` skip, the transaction boundary marked, and the CLI's
  `console.log` replaced by its log events.
- **`README.md`** — the "no client-side JavaScript" claim in the opening
  paragraph (there are 21 lines of it); first-run timings re-measured; the
  security-headers "no page has a script tag" claim; the seeded-accounts
  sign-in line (flash vs outbox); `make routes`/`image`/`run-image` added to the
  Commands table; eslint on `recommendedTypeChecked`; **1,161 tests / 99.42% →
  1,536 / 99.57%**; the CI section rewritten for the three-step workflow; the
  smoke description extended; the `better-sqlite3` revert note (twice) rewritten
  — that package is not a dependency any more; the CHECK-constraint `make fresh`
  note; **"zero `<script>` tags across all 57 templates" → three tags in 66
  templates, all the same `/app.js`**; the layout block rewritten; the known-gaps
  paragraph rewritten.
- **`docs/README.md`** — the architecture and admin one-liners now name the
  sections that were added.

### `docs/review.md` — the gaps list

Seven gaps became ten. Dropped nothing that is still true; added four that the
refinement batch either created or exposed, and stated the six things this batch
closed so a reader of an older copy is not misled.

1. No SMTP (kept, unchanged).
2. **New — the event bus is in-process**, so `<prefix>/events` streams only see
   writes handled by their own instance. `app.events` is a `node:events`
   `EventEmitter`; nothing else in the app has a single-instance constraint.
3. **New — the storefront 404 renders signed-out**, the wart IMPRV-001
   documented.
4. **New — `listing.published` is the one business event with no log line**, the
   loose end IMPRV-003 and IMPRV-002 both left.
5. SVG placeholders (kept).
6. Free-text tracking (kept).
7. No admin assignment (kept; corrected the file it names —
   `open-support-conversation.ts`, not `open-conversation.ts`).
8. No attachments or archive (kept).
9. Migration `down()` uncovered (kept; "nine migration files" → eleven).
10. Platform-wide payout only (kept).

`## Suggested next steps` renumbered to match, with entries for the three new
gaps.

### Smoke test

`app/test/smoke.test.ts` already covered `/health` (FEAT-011 added it there).
Added two tests, 6 → 8:

- **the outbox walk**: build the app with `outboxMagicLinkDelivery` and
  `showsDebugMagicLinks: false`, ask for a seller link, assert the page that
  answers prints no link, assert one pending `outbox_messages` row holding a
  64-hex magic URL, assert it is listed on `/admin/outbox`, `drainOutbox` it
  into a temp directory, and assert the `.eml` on disk carries the `To:` header
  and the URL and that the row is stamped.
- **one SSE frame**: `app.listen({ port: 0 })` and a real `fetch`, because
  `app.inject` buffers and the stream never ends; asserts
  `text/event-stream` and `retry: 3000\n\nevent: unread\ndata: 0\n\n`. Reads in
  a loop rather than asserting the first chunk — the two frames arrive coalesced
  over a socket and separately in memory.

### Validation, with the exact commands

All from `prototype/node`, with the stack under its own project name and a
scratch override binding 4001 (port 4000 on this machine is held by another
container).

1. **Clean first run from an empty tree.**
   `rm -rf src/node_modules src/public/app.css src/storage/outbox && rm -f src/storage/*.sqlite3*`
   then
   `docker compose -p art-store-refine -f docker-compose.yml -f <override> up -d --build`.
   `docker compose ... ps` reported `Up 29 seconds (healthy)`. Log shows
   `npm ci` (230 packages), eleven migrations applied from nothing, `seed.admins`
   count 2, `seed.demo_data` 4 sellers / 29 listings / 5 customers / 3 orders /
   98 page-view rows / 4 conversations / 11 messages / 1 FAQ, Tailwind, then
   `Server listening`.
2. **Route table.** `docker compose ... run --rm --no-deps app npm run routes` —
   the full route tree and plugin tree; used as the checklist for the walk.
3. **Curl walk** (`scratchpad/walk.sh`, `BASE=http://localhost:4001`):
   **75 checks, 0 failures**, and `docker compose ... logs app | grep -c
   '"statusCode":5'` → **0**, with no `"level":50` line anywhere. It covers every
   GET route `npm run routes` prints, for all three actors:
   - `/health` → **200 `application/json`**,
     `{"status":"ok","checks":{"database":"ok","migrations":"current"},...}`.
   - Sign-in through the debug magic-link flow: `POST /seller/login` →
     read the link out of the rendered page → follow it (`maya@example.com`);
     same for `/admin/login` (`jonathan-beebe@outlook.com`); the customer
     verified from a live checkout.
   - 16 storefront URLs, 15 seller pages, 18 admin pages, plus six admin filter
     tables submitted with their empty-string "all" value.
   - `/nope` → **404 `text/html`**, the storefront's own page; `/seller/nope`
     and `/admin/nope` likewise.
   - `POST /login` with `email` submitted twice → **400 `text/html`**.
   - `/events`, `/seller/events`, `/admin/events` → **200 `text/event-stream`**,
     each pushing `retry: 3000` then `event: unread`.
   - A real guest checkout: `POST /cart/:slug` → `GET /cart` →
     `POST /checkout` (302) → order in `pending_verification` → magic link →
     `/orders/:id/pay` 200.
   - Guarded pages redirect rather than render for a signed-out visitor
     (`/checkout`, `/account` → 302); `/support` and `/seller/support` 302 into
     the thread they open, which is their design.
4. **Outbox delivery.** Override extended with
   `environment: { MAGIC_LINK_DELIVERY: outbox }`, `up -d`, waited for healthy.
   `POST /admin/login` printed **no** link into the page (`grep -c auth/magic`
   → 0); the row was in `outbox_messages` with `delivered_at` null; following
   its URL signed the admin in; `/admin/outbox` **200** listing the address and
   `/admin/outbox/:id` **200** with the link. Then
   `docker compose ... run --rm --no-deps app npm run outbox` drained **17**
   messages to `src/storage/outbox/*.eml` and logged `outbox.drain_run`.
   `17.eml` read back as a well-formed RFC-5322 message with `From`, `To`,
   `Subject`, `Date`, `Message-ID`, `MIME-Version`, a `text/plain; charset="utf-8"`
   content type, and the URL in the body.
5. **`npm run check` in the container.**
   `docker compose ... run --rm --no-deps app npm run check` → exit 0.
   **1,536 tests, 1,536 pass, 0 fail. Coverage 99.57 lines / 97.22 branches /
   99.47 functions** against the 95 / 90 gate.
6. **`npm run check` on the host.** `rm -rf node_modules && npm ci &&
   npm run assets && npm run check` from `src` → exit 0, same 1,536 / 0 and the
   same coverage.
7. **`make smoke` equivalent.**
   `docker compose ... run --rm --no-deps app node --test app/test/smoke.test.ts`
   → **8 tests, 8 pass, 0 fail**, 1.57s.
8. **`make docs-check`.** `./docker/docs-check.sh` → **21 diagram(s) rendered,
   0 failed**, exit 0. Run twice: once mid-way and once after the last diagram
   edit.
9. **Production image.** `docker build --target runtime -t art-store-node .` →
   exit 0, **289MB**, `npm ci --omit=dev` installing **87 packages**.
   - `docker run ... art-store-node node app/db/migrate.ts` over a named volume
     applied eleven migrations to `/var/www/src/storage/production.sqlite3`.
   - `docker run -d -p 4002:4000 -v <vol>:/var/www/src/storage
     -e COOKIE_SECRET=<32 chars> -e MAGIC_LINK_DELIVERY=outbox art-store-node`
     → `/health` **200 `application/json`**
     `{"status":"ok","checks":{"database":"ok","migrations":"current"},"uptimeSeconds":0}`,
     `GET /` **200 `text/html`**.
   - With no `COOKIE_SECRET` it refuses to boot:
     `Error: COOKIE_SECRET is required when NODE_ENV=production: the identity
     cookies are signed with it, and a shared default makes an admin cookie
     forgeable.`
   - With `MAGIC_LINK_DELIVERY=flash` it also refuses:
     `Error: MAGIC_LINK_DELIVERY=flash prints the sign-in link into the page
     that asked for it, which makes it a development-only delivery. Choose
     another delivery when NODE_ENV=production.`
   - `docker stop` logged `shutdown: draining` then `shutdown: complete` —
     BUG-006 and FEAT-011 both confirmed from the outside.
10. **Cleanup.** `docker compose -p art-store-refine ... down -v`, the
    `art-store-refine-app` image removed. `art-store-node` kept, as FEAT-013
    intended. The other stack on port 4000 was never touched.

### Left alone deliberately

- **`package.json` scripts.** `check` and `test:ci` both stay. Changing `check`
  to call `test:ci` would put an lcov file and a `coverage/` directory into
  every local run for no local benefit; moving the three steps into the workflow
  costs nothing and reads better in the job list.
- **`record-page-view.ts`'s `count: 1`** — reasoned above.
- **The ten known gaps.** Each is a real limit of the prototype, not an
  oversight to fix under this ticket; each has a numbered next step.
- **The `docs/review.md` brief-to-route map.** Every route and every test file
  it names still exists — checked by resolving every backticked `app/**` path
  against the tree. Only the two `mail-magic-link-delivery` references were
  stale, and the `better-sqlite3` row, which is now an honest "done, by a
  different driver" entry rather than a false claim.

Test counts: 1,534 → **1,536** (the two smoke tests). Coverage 99.57 / 97.22 /
99.47, unchanged by the two dropped insert literals.

### Second pass: an independent audit of the rewritten docs

After the rewrite, ran a read-only sweep of every backticked identifier, route,
and number in `README.md` and `docs/` against `src/app`. Six more mismatches
found and fixed, all of them older than this ticket:

1. `docs/admin.md` named `formatLabel` as the third admin view helper; it is
   `statusLabel` (`app/sites/admin/page.ts`), and `formatLabel` exists nowhere.
2. `docs/data-model.md` named `missingConversationParts` as the pure check over
   a conversation's participant and subject columns; no such function exists.
   Replaced with `participantColumnsOf` / `subjectColumnOf`
   (`app/core/messaging/conversation-kind.ts`).
3. Two package counts contradicted each other — "230 packages" in the run
   narratives, "260 packages" in the stack notes. Both are true of different
   things: the lockfile holds 260 entries and `npm ci` reports installing 230,
   skipping platform-specific optional binaries. Both places now say which.
4. "Every column holding a string union carries a `CHECK` constraint" was false
   for one column: `page_view_counts.site` is typed `PageViewSite` but its
   migration adds no check. Stated as the one exception in all three places that
   made the claim, and marked in the ER diagram, rather than adding a constraint
   — that would be a migration edit inside IMPRV-004's territory and would
   invalidate the clean run already recorded above.
5. `README.md`'s Commands table described `make fresh` as "run fresh, then seed,
   then restart". The target stops the app **first**, which is the whole point:
   a server left running holds the deleted database file open. Corrected.
6. `README.md` and `docs/architecture.md` both described `app/sites/auth/` as
   having `routes/` and `views/` directories. It is three flat files and has no
   pages of its own.

Four smaller corrections in the same pass: the `changed` event skips `OPTIONS`
as well as `GET`/`HEAD`; the stack table's runtime cell said `node:24-bookworm-slim`
where the `Dockerfile` pins `node:24.19.0-bookworm-slim`; the README's mail
header list omitted `Content-Transfer-Encoding: 8bit`; and both layout blocks
missed `app/test/log-lines.ts`.

`make docs-check` re-run after these edits: 21 diagrams, 0 failed. Every
backticked `app/**` path in every doc resolves to a file that exists.
