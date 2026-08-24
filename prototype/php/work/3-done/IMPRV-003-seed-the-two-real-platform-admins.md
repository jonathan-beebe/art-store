---
id: IMPRV-003
type: improvement
status: resolved
created: 2026-08-23
---

# IMPRV-003: Seed the two real platform admins

## Problem
`database/seeders/AdminSeeder.php` seeds one fictional admin (`admin@example.com`, "Reese Calloway"). The Node prototype (`prototype/node/src/app/db/seed-admins.ts`, `SEEDED_ADMINS`) seeds the two real operators — Jonathan Beebe (`jonathan-beebe@outlook.com`) and Anna Schmunk (`annaschmunk@pm.me`) — and the prototypes are compared side by side on the same demo data.

## Goal
The PHP demo admits the same two operators the Node demo does.

## Outcome
- After `make fresh`, `admins` holds exactly Jonathan Beebe (`jonathan-beebe@outlook.com`) and Anna Schmunk (`annaschmunk@pm.me`), seeded in that order so Jonathan is the first admin by id, matching Node's `SEEDED_ADMINS[0]` being the one demo data hangs off.
- Both addresses receive a magic link at `/admin/login`; the seeded support threads open against Jonathan.
- The unadmitted-address debug notice names a real seeded admin address.
- Seeder idempotence holds (second run adds nothing).
- README's seeded-accounts table lists both admins.
- `make check` green; coverage 100%.

## Why it matters
Reviewers sign in as themselves on the Node demo; the PHP demo asking for a made-up address breaks the side-by-side.

## Discovery notes
- Node's list shape (`SEEDED_ADMINS`, add-what-is-missing, first entry is the demo anchor) is the reference; a PHP constant list on `AdminSeeder` replacing the single `EMAIL` keeps callers one hop away.
- Grep for `AdminSeeder::EMAIL` and `admin@example.com` and `Reese Calloway` across src (controller debug notice, MessagingSeeder, tests, README, docs) — every caller needs the list or its first entry.
- `Admin::platformAdmin()` is first-by-id; the seeder order is what makes that Jonathan.
- `DatabaseSeederTest`/`MessagingSeederTest` pin admin counts and names — update the pins to the new truth.

## Related work
- FEAT-015 (AdminSeeder), IMPRV-002 (debug notice names the seeded address)

## Working
- `AdminSeeder::EMAIL` replaced with `AdminSeeder::ADMINS` — a `list<array{email: string, name: string}>` matching Node's `SEEDED_ADMINS` shape, seeded in a loop via `Admin::firstOrCreate`. `ADMINS[0]` is Jonathan Beebe (`jonathan-beebe@outlook.com`), `ADMINS[1]` is Anna Schmunk (`annaschmunk@pm.me`) — seeded in that order, so `Admin::platformAdmin()` (first by id) resolves to Jonathan and the seeded support threads open against him, unchanged.
- Every caller of `AdminSeeder::EMAIL` moved to `AdminSeeder::ADMINS[0]['email']`: `AdminLoginController`'s unadmitted-address debug notice, `MessagingSeeder`'s support-thread admin lookup, and the three test files that referenced the single seeded address (`AdminLoginControllerTest`, `MessagingSeederTest`, plus `DatabaseSeederTest`'s admin-count assertion, rewritten to check both seeded addresses).
- `AdminSeederTest` rewritten: asserts both admins exist, verified, in id order (`jonathan-beebe@outlook.com` then `annaschmunk@pm.me`), and that a second seed run leaves the count at 2. First draft indexed the returned Eloquent collection with `$admins[0]->email` / `$admins[1]->email`, which PHPStan flagged (`property.nonObject` — `get()` collection items type as possibly-null); switched to `$admins->pluck('email')->all()` against the expected list.
- README's seeded-accounts table: "one admin" → "two admins", one row → two rows (Jonathan Beebe / Anna Schmunk with their real addresses), Reese Calloway row dropped.
- No deviations from the ticket's outcome or discovery notes. Nothing found-not-fixed.
- Verification, `make fresh` then curl against `http://localhost:8000/admin/login`, each in its own cookie jar:
  - POST `jonathan-beebe@outlook.com` → "Check your email" + "Debug magic link:" with a `/auth/magic/<token>` link.
  - POST `annaschmunk@pm.me` → same, its own token.
  - POST `random-address@example.test` → "No admin account exists for random-address@example.test. The seeded admin address is jonathan-beebe@outlook.com."
- `make check`: 1111 tests, 2501 assertions, green (2497 → 2501: +4 assertions from the rewritten `AdminSeederTest`/`DatabaseSeederTest` cases; test count unchanged at 1111). `make coverage`: 100.0%.
