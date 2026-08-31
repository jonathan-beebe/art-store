---
id: FEAT-035
type: feature
status: open
created: 2026-08-30
---

# FEAT-035: Automated hourly snapshots and a nightly backup

## Problem

The prototype is about to ship with SQLite as its production database and
there is no backup of any kind. `storage/production.sqlite3`,
`storage/logs.sqlite3`, and the uploaded listing images in
`storage/app/public` exist in exactly one place — the Render disk — and
nothing copies them anywhere, on any schedule.

Nothing in this deployment schedules anything at all. `composer run deploy`
(`src/composer.json`, the `deploy` script) chains `migrate` → `db:seed` →
`artisan serve`; there is no scheduler process in the container. `payouts:run`
and `orders:sweep` exist as commands and make targets and have only ever been
run by hand. So "run a backup every hour" has nowhere to run.

The disk cannot be reached from outside, either. Render attaches a persistent
disk to exactly one service at runtime, so a Render Cron Job in its own
container cannot see it; Render SSH is paid-plan only and needs Dockerfile
changes for a `www-data` whose shell is `nologin`. Whatever copies the
database has to run inside the web service and push the result out.

## Goal

Losing the Render disk stops being an event that ends the store.

## Outcome

Two jobs run unattended in the deployed container, and their results are
visible without shell access:

- **Hourly**, a snapshot of both SQLite databases lands on the disk, and the
  most recent twenty-four are retained. An hour-old mistake — a bad
  migration, a wrong refund, a bulk edit — is recoverable.
- **Nightly**, a single archive carrying both databases and the uploaded
  images lands on the disk and is uploaded to Cloudflare R2. The most recent
  seven are retained locally.
- Every run is verified before it counts as succeeded: each snapshot reopens
  cleanly, passes `PRAGMA integrity_check`, and carries a row count for its
  principal tables and a SHA-256 for every member.
- Every attempt — succeeded, failed, or still running — is recorded and
  readable at `/admin/backups`: when, which job, how big, how long, whether
  it reached R2, and the error if it did not.
- An operator can run either job on demand from that page and download a
  local artifact from it.
- A run that would leave the disk near full refuses and says so, rather than
  filling the volume and taking the store down with it.
- With the R2 settings absent the nightly job still runs and still writes
  locally, recording that upload was not configured. The feature is usable
  before the bucket exists.
- Backups keep happening when no laptop is awake.

## Why it matters

This is the difference between shipping and gambling. Every other piece of
the PHP prototype is production-shaped — the escrow ledger, the alignment
contract, the coverage gate — and the entire commercial record of the
platform sits on one disk with no copy. A Render disk is a single point of
failure, a deploy on a disk-attached service is a stop-then-start, and SQLite
is a file that a bad migration can ruin in one statement.

The hourly and nightly jobs answer two different disasters and the prototype
needs both. Hourly answers "we broke the data an hour ago" — the common case,
where the disk is fine and the contents are not. Nightly-to-R2 answers "the
disk is gone" — rarer, unrecoverable, and the one where having only a copy on
the same volume means having nothing.

Fixing the missing scheduler is worth the ticket on its own: `orders:sweep`
cancels stale unverified orders and prunes the log store, and `payouts:run`
settles seller money. Both are described as scheduled jobs everywhere in the
docs and neither has ever run without a person typing it.

## Discovery notes

Advisory. `docs/backups.md` is the design and carries the reasoning, the
directory layout, the manifest shape, the configuration table, and the
failure-mode table. Read it first; the notes below are the parts most likely
to bite.

**Scope: this ticket is the mechanism, not the hardening.** Deliberately out
of scope, recorded in `docs/backups.md` and left for follow-on tickets:
archive encryption before upload, scoping the R2 tokens, and the exposure of
the admin download route. A private bucket is the bar here. Restore has its
own ticket.

**One command, two kinds.** `backup:run --kind=hourly|nightly` with the kind
selecting contents, retention, and whether the artifact uploads. Two commands
would be two implementations that drift.

**`VACUUM INTO` is the snapshot mechanism, not `cp` and not `tar` over the
live file.** Either can catch a page mid-write, and a copy that misses the
`-wal` sidecar is a database missing its newest commits. Verified in the
image (SQLite 3.46.1): the destination binds as a parameter, so the path is
never interpolated into SQL, and an existing destination is refused outright —
which is why writing into a temp directory and renaming into place only after
verification leaves nothing that looks finished after a crash. `VACUUM` cannot
run inside a transaction. There is no `sqlite3` CLI in the image and no need
for one; `tar` and `gzip` are both present.

**Two databases, two handles.** The commerce file comes off Laravel's
connection; the log store has its own PDO handle on `LogStore::$connection`.
`LogStore` also buffers rows in memory and flushes at 256 rows or process
exit, so a snapshot cannot see lines buffered in other processes. Flush the
runner's own store first and let the rest go — the mirror is allowed to be a
few lines behind, and a later reader should not mistake the gap for
corruption.

**The disk floor is the part that protects the store.** An hourly job copying
the database onto the same volume is, unmanaged, a slow-motion outage:
retention lags, the volume fills, and SQLite's next write fails on a database
that was healthy an hour ago, at 3am. Retention and a `disk_free_space()`
check belong *before* the write that would need the room. Refusing a backup is
recoverable; filling the disk is not.

**Row counts in the manifest are what make an empty backup visible.** A file
that opens, passes `integrity_check`, and holds zero orders is a successful
backup of nothing. Comparing the count against the previous run turns that
into a number on a page rather than a discovery during a restore.

**The sidecar manifest is a contract, not a convenience.** FEAT-036's restore
tool lists and verifies archives with no Laravel boot and no database — it
reads the `.manifest.json` beside each artifact, on the disk and in R2. So
the sidecar accompanies every artifact in both places, is self-describing
(kind, instant, sizes, hashes, row counts — everything the tool's listing and
confirmation pages show), and its shape changes only additively. The
`backup_runs` table is the admin panel's read model; the sidecar is the
restore tool's.

**The scheduler.** `php artisan schedule:work` has to run beside the server in
the same container, which means replacing the composer `deploy` script with a
`src/bin/deploy` — the shape the Rails prototype already uses. Render's Docker
Command passes no shell, so it invokes the script directly and the shebang
supplies one. `withoutOverlapping()` matters more than usual: a long nightly
must not have the next hourly start writing underneath it. Keep both jobs
hand-runnable (`make backup KIND=…`, and the admin's run action) so the
scheduler is the convenience rather than the only path.

**Interaction with IMPRV-019.** That ticket moves the runtime image from
`artisan serve` to FrankenPHP. Backgrounding the scheduler with `&` and a trap
is adequate beside `artisan serve`; under FrankenPHP the container runs two
long-lived processes and wants real supervision — s6 or supervisord. Whichever
of the two tickets lands second owns reconciling the process model and
updating the other's notes.

**Alignment.** `docs/alignment.md` §2.3's event vocabulary is closed, so
`backup.run` and `backup.upload` have to be added there before this prototype
may emit them — otherwise the one job that runs unattended at 3am is invisible
to `/admin/logs`. Also: `backup_runs` → `bkp` in the §1 prefix table, the
admin routes into §5, and `backup` onto §6.1's "scheduled jobs, by hand" row.
Node and Rails follow.

**R2 is an S3 disk.** `league/flysystem-aws-s3-v3` is the one new dependency.
`region` is `auto` and path-style endpoints are on. Worth departing from the
other disks in `config/filesystems.php` on one point: Laravel's default
swallows a write failure and returns `false`, and a backup that reports
success because nobody checked a return value is the exact failure this
feature exists to prevent. A `HEAD` after upload is what turns "the write
returned" into "the object is there, at the size we sent".

**`config/backups.php` should refuse the process on a malformed value at
boot**, the way `config/log_store.php` and `config/rate_limits.php` already
do. A bad retention setting should stop the container, not the 3am job that
needed it.

## Related work

- `docs/backups.md` — the design this ticket implements.
- `FEAT-036` — the standalone restore tool; depends on the archive layout and
  the sidecar manifests fixed here, and owns restore entirely — the
  `/admin/backups` panel built here keeps to viewing history, running a job,
  and downloading an artifact, plus one link to the tool.
- `IMPRV-019` — FrankenPHP in the runtime image; shares the process-model
  decision for supervising the scheduler.
- `docs/log-store.md` — the second SQLite file, its buffering, and the
  `/admin/logs` viewer this feature's panel is modelled on.
