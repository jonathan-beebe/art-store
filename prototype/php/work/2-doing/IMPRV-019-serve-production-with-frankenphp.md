---
id: IMPRV-019
type: improvement
status: open
created: 2026-08-30
---

# IMPRV-019: Serve production with FrankenPHP instead of artisan serve

## Problem

The production image serves HTTP with `php artisan serve`. `Dockerfile`'s
`runtime` stage ends with `CMD ["php", "artisan", "serve", "--host=0.0.0.0",
"--port=8000", "--no-reload"]`, and `docker-compose.yml` runs the same command
for dev. `artisan serve` is a wrapper around PHP's built-in web server, which
PHP's manual describes as not intended for a public network.

What that costs in this codebase:

- **One request per worker, blocking.** `PHP_CLI_SERVER_WORKERS=16` forks
  sixteen processes, each handling one request at a time.
  `docker-compose.yml:12-24` documents the consequence in its own comment: the
  unread-badge SSE stream holds a worker for up to
  `UnreadCountStream::LIFETIME_SECONDS`, and 16 was chosen to survive twelve
  concurrent streams — leaving roughly four workers for page loads. The
  ceiling is a fixed process count, and the app's own live-badge feature eats
  most of it.
- **No request timeouts, no process limits, no worker recycling.** There is no
  equivalent of `request_terminate_timeout`, `max_children`, or
  `max_requests`. A hung request holds its worker until the process is killed.
- **Static assets are served by PHP.** Every CSS file, JS bundle, and uploaded
  listing image under `storage/app/public` occupies a PHP worker.
- **No slow-client protection.** A client trickling bytes holds a worker for
  as long as it likes, against a single instance with sixteen of them.
- **Request and path handling is not hardened** against hostile input.

Checked and *not* a factor: OPcache is active under the `cli-server` SAPI
despite `opcache.enable_cli=0`, so this is not a compilation-overhead problem.

## Goal

The prototype can take public traffic on the HTTP server it ships with,
rather than on one whose own documentation rules that out.

## Outcome

The production image serves the storefront, seller portal, and admin site
under a server built for public traffic:

- A request for a static asset is answered without occupying a PHP process.
- Concurrent request capacity is no longer a fixed count of forked workers,
  and `PHP_CLI_SERVER_WORKERS` no longer governs it.
- A dozen open SSE unread-badge streams leave page loads responsive.
- `make image` builds and `make run-image APP_KEY=…` answers plain http on the
  non-colliding host port; `/up` passes; `make check` is green.
- Behind a proxy, forwarded scheme and client IP still reach the app —
  magic-link URLs are generated with the right scheme and rate-limit keys
  still distinguish clients.
- The README's Deployment section and the Render Docker Command describe what
  the image actually does.

## Why it matters

This is the ticket that separates "a prototype that has been deployed" from
"a store that can be given to customers". Everything else in the PHP
prototype is production-shaped — the coverage gate, the alignment contract,
the log store, the escrow ledger — and the front door is a development
server. The concrete failure is availability: sixteen blocking workers, four
of them practically free, with no timeout and no slow-client defence, is a
single instance that a handful of parked connections can make unusable
without anyone intending harm.

It also blocks the backup work. `docs/backups.md` needs a scheduler process
living beside the server in the same container, and the right way to
supervise two long-lived processes depends on what the server is.

## Discovery notes

Advisory. The direction is decided — FrankenPHP — but the shape of the change
is the maker's call.

**Why FrankenPHP over the alternatives.** Caddy with PHP embedded: one
process, one binary, serves static files itself, and it replaces the base
image and the CMD without touching application code. The conventional
alternative, nginx + php-fpm in one container, is better understood but adds
a supervisor, an nginx config, and an fpm pool config.

**Keep out of scope: Laravel Octane and FrankenPHP's worker mode.** Both keep
the application resident between requests. `App\Logging\LogStore` assumes one
request per process — it buffers rows in memory and flushes via
`register_shutdown_function` (see `docs/log-store.md`, "The second
database"). A resident worker breaks that assumption, and probably others.
Classic non-worker mode gets the concurrency and static-file wins with the
per-request lifecycle intact. Worker mode is worth its own ticket, after an
audit.

**Things the current runtime stage does that need to survive:** document root
at `public/` with the baked `public/storage` symlink to `storage/app/public`;
`USER www-data`; the `/var/www/src/storage` VOLUME; the `/up` HEALTHCHECK;
port 8000; and the gd / intl / pdo_sqlite / zip extensions (gd is load-bearing
for the listing-upload content check, per the Dockerfile's comment).

The dev compose stack can stay on `artisan serve` — the bind-mount workflow
is not what this ticket is about, though matching dev to prod is a reasonable
thing for the maker to argue for.

`docs/alignment.md` needs no change: §6.1 fixes `image` and `run-image` as
targets, not what serves inside the image. Node and Rails are unaffected.

**Process model, shared with the backups design.** `docs/backups.md` proposes
replacing the composer `deploy` script with `src/bin/deploy`, which
backgrounds `php artisan schedule:work` with `&` and a trap, then `exec`s the
server as PID 1. That shape is adequate next to `artisan serve`. Under
FrankenPHP the container runs a server and a scheduler as two long-lived
processes and wants real supervision — s6 or supervisord — rather than `&`.
Whichever of the two tickets lands second owns reconciling this, and should
update the other's doc.

Also touched: `README.md`'s Deployment section (it names `composer run
deploy` and the built-in server, and has to change together with the Render
Docker Command), and `docs/architecture.md` if it describes the runtime
process model.

## Related work

- `docs/backups.md` — the backup design that needs a supervised scheduler
  process in the same container; shares the process-model decision.
- `RSRCH-001` — the performance baseline; the twelve-concurrent-stream figure
  that set `PHP_CLI_SERVER_WORKERS=16` comes from its M8.
- `IMPRV-017` — request lines carry query count and time, the instrumentation
  that makes a before/after on this measurable.
