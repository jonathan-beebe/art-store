---
id: FEAT-036
type: feature
status: open
created: 2026-08-30
---

# FEAT-036: A standalone restore tool, linked from the admin

## Problem

FEAT-035 produces hourly snapshots and nightly archives. Nothing consumes
them. A backup that has never been restored is a file nobody has read, and
the prototype has no path from an archive back to a running store.

The gap is widest exactly where it matters. On a fresh deploy — a new Render
service, an empty disk, seeded demo data and nothing else — bringing the real
store back means replacing `storage/production.sqlite3`,
`storage/logs.sqlite3` and `storage/app/public/` by hand. There is no shell to
do it from: Render SSH is paid-plan only and needs Dockerfile changes for a
`www-data` whose shell is `nologin`, and a Render Cron Job cannot see the web
service's disk. The archive is reachable — in Cloudflare R2, or on a laptop —
and the running app is reachable, and there is no way to introduce one to the
other.

An earlier draft of this ticket put the restore control inside the admin
site. Working that design produced four hazards, and three of them are
symptoms of one architectural fact — the restore instrument living inside the
thing being restored:

- The admin's session lives in the `sessions` table, in the file being
  replaced, so the restore signs out the person running it mid-flight.
- PHP worker processes hold the SQLite file open; renaming a new file over it
  leaves those handles on the old inode, so the app keeps serving the data
  that was just replaced until it restarts — taking the restore's own UI down
  at the moment the operator most wants to watch it.
- A `restore_runs` table in either SQLite file is erased by the thing it
  records.
- The fourth hazard is the disqualifying one: an in-app restore is reachable
  only while the app boots. A corrupt commerce database — the disaster this
  feature exists for — 500s every Laravel route, the admin site included. The
  restore button dies with the patient.

## Goal

A backup becomes something we have proven we can come back from, on a server
that has never held the data before — including when the database is too
damaged for the app to boot.

## Outcome

Restore runs in a standalone tool that shares the container and the disk with
the app and shares nothing else — no Laravel boot, no commerce database, no
log store, no `sessions` table. An operator with the tool's credential and no
shell can bring a store back:

- The tool answers, lists archives, and runs a restore **when the commerce
  database is corrupt, absent, or mid-replacement**. Its own pages never read
  the data being restored.
- The tool is reachable through the service's one public port, on its own
  path, behind its own credential. The credential comes from the environment
  — set in the Render dashboard, independent of `APP_KEY`, the databases, and
  everything a restore replaces. The admin site links to the tool; the link
  carries no secret, and holding an admin session grants nothing at the tool.
  Failed attempts are rate-limited by the tool itself.
- The tool lists restorable archives from both sources — the local
  `storage/backups/` directory and Cloudflare R2 — by reading each archive's
  sidecar manifest, newest first, with the date, kind, size, and the order /
  listing / seller / customer counts of each. An archive can also be uploaded
  from the operator's machine.
- Before anything is overwritten, the chosen archive's manifest is shown back
  and confirmed against, so restoring three-week-old data is a decision
  rather than an accident.
- The archive is verified before the live data is touched — hash against the
  manifest, `PRAGMA integrity_check` on the extracted databases, staged in a
  scratch directory. A verification failure stops the restore with the store
  exactly as it was.
- The current state is captured first, so restoring the wrong archive is
  itself recoverable — including a byte-copy capture of a database too
  corrupt to snapshot, kept as evidence.
- The restore replaces both databases and the uploaded images together, and
  the storefront and seller portal show a clear "we'll be back" page for the
  duration. The maintenance state lives in a file on the disk — a contract
  the app's middleware reads and the tool writes — so it survives the
  database being replaced underneath it and cannot be un-set by the restore.
- Progress is visible in the tool throughout — download, verify, swap — and
  survives a page reload, because nothing the progress page renders depends
  on the data mid-swap.
- When the swap is done, the tool says plainly what remains: restart the
  service so every PHP process reopens the new files, then return the store
  to operational mode. Both the restart step and the mode change are
  deliberate operator actions, not side effects.
- What happened is recorded in a file the restore never replaces — which
  archive, when, verified against what, and the result — and the tool shows
  that history.
- A restore in progress cannot be started twice.
- Restoring the latest nightly into the local dev stack is a single make
  target, so the archive gets read on an ordinary Tuesday rather than only
  during an incident.

## Why it matters

A backup is a claim; a restore is the evidence. FEAT-035 will produce
archives every night, and until one has been turned back into a running store
nobody knows whether they hold what they should, whether the images match the
database, or whether the procedure fits in the access we actually have. The
first attempt should not happen during an outage, at speed, by someone
reading a wiki page.

The standalone shape is what makes the tool trustworthy in every scenario it
serves. The three in-app hazards — session loss, the handle-swap blackout,
the self-erasing record — each had a workaround; the unbootable-app case had
none. Separating the instrument from the patient closes all four at once,
and turns "watch the process" from a hard problem into the default: the
progress page depends on nothing being replaced.

The Render setup — no shell, a disk visible to one service, one public port —
means the container has to carry its own recovery instrument. The tool is
that instrument, and its independence from the app is the property every
other requirement hangs off.

## Discovery notes

Advisory. This remains the one feature that deletes production data on
purpose; the notes are about ordering and about what the tool must never
depend on.

**What the tool may depend on:** the disk, the environment, the sidecar
manifests, `tar`/`gzip`, PDO-SQLite for `integrity_check`, and the R2
credentials already in the environment for FEAT-035. **What it must not
depend on:** the Laravel boot, either SQLite file, the `sessions` table,
Vite-built assets, or anything `composer run deploy` does. Every dependency
it takes on is a way it can be down when it is needed.

**Serving shape, and the IMPRV-019 ordering.** Render routes public traffic
to one port per web service, so the tool shares the front door on its own
path. Under FrankenPHP that is a Caddy route: the tool's path to a
self-contained PHP entry point, everything else to Laravel's
`public/index.php`. Under `artisan serve` there is no equivalent routing —
which makes IMPRV-019 a dependency of this ticket, and settles the order the
two land in. The supervision question IMPRV-019 carries (server + scheduler)
gains no third process: the tool is request-driven PHP behind the same
server.

**The credential is an environment secret; minted tokens were considered and
rejected.** A token minted by the admin app depends on the app booting, on
`APP_KEY`, and on session infrastructure — the exact things the disaster case
takes out. One long secret in the environment survives everything a restore
touches and is rotated in the Render dashboard. The admin link is a
convenience pointer; keep the secret out of the URL (it lands in logs), and
give the tool its own small rate-limit state on disk. If the tool keeps a
session at all, sign a cookie with the secret itself.

**The tool watches; a background step does the work.** A restore outlives a
comfortable request. Progress written to a file on disk, polled by the page,
survives reloads and the operator's coffee break, and is the same mechanism
that makes "a restore in progress cannot start twice" a state rather than a
lock.

**Order of operations, unchanged from the earlier draft because getting it
wrong destroys data:** verify the archive → capture current state → enter
maintenance → stage and verify the extracted databases → swap → record →
operator restarts. A failure before the swap leaves the store untouched.
When the current database is too corrupt for `VACUUM INTO`, fall back to a
byte copy — corrupt evidence beats no evidence. After entering maintenance,
allow a beat for in-flight requests to drain before capturing.

**The restart at the end stays an operator action.** Render's restart button
is the honest mechanism (a disk-attached service already stop-then-starts on
deploy). Having the tool kill PID 1 to force a container restart works and
could be offered behind its own confirmation, but the deliberate-by-default
stance should hold: this feature never restarts anything as a side effect.

**Maintenance state is a file contract, now shared with the app.** The
storefront and seller portal route groups gain middleware that reads it; the
admin group does not. Three states — operational, maintenance, restoring —
so "a restore is running" is something the app can refuse writes under and
the tool can refuse a second restore under. The tool owns transitions during
a restore; an admin-side toggle for planned maintenance can exist
independently and must not be able to clear the `restoring` state.

**Testing without booting.** The runtime entry point skips Laravel, but the
tool's logic — manifest parsing, verification, staging, the swap plan —
belongs in plain PHP classes under their own PSR-4 prefix so Pest covers them
like everything else. The entry point stays a thin dispatcher, the same
altitude rule the app already follows.

**The tool's own output.** It cannot write the log store and should not try;
emit §2.1-shaped JSON lines to stdout so container logs stay uniform, and
treat the on-disk restore record as canonical. The record is append-only and
the only account a later reader will have.

**Uploads from a laptop** hit `upload_max_filesize`, `post_max_size`, and
request timeouts regardless of who serves them; a stated size limit with a
clear message beats a silent 413. R2 remains the primary source.

**Bootstrap, revisited.** The earlier draft leaned on seeded admins and
magic-link banners to make `/admin` reachable on a fresh server. The tool
weakens that dependency to a convenience: the operator can go straight to the
tool's path with the environment secret, data or no data, admin or no admin.
The seeding chain keeps its idempotence for the app's own sake.

**Alignment.** Restore leaves `docs/alignment.md` §5's admin feature set —
the admin site's part is one link. Maintenance mode is the piece Node and
Rails would need to match, and worth raising before building. §2.3 gains
whatever the maintenance transitions emit; the tool's own lines reuse the §2.1
payload shape without needing new vocabulary if `restore.run` is added there
once.

**Out of scope**, worth their own tickets if wanted: partial restore (images
only, one table), point-in-time recovery between snapshots, restoring onto a
running instance without a restart, and the tool driving Render's API to
restart the service itself.

## Related work

- `FEAT-035` — produces the archives and sidecar manifests this consumes; the
  manifests are what let the tool list and verify archives with no database.
- `IMPRV-019` — FrankenPHP in the runtime image; provides the path routing
  that gives the tool its own entry point, so it lands first.
- `docs/backups.md` — the design; its "Restore" section describes the manual
  sequence this tool automates and points here.
- `docs/identity.md` — the seeded admins and magic-link banner the earlier
  draft depended on; now a convenience rather than the recovery path.
