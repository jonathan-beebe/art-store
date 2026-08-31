---
id: IMPRV-019
type: improvement
status: resolved
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

## Working

- 2026-08-31 — re-validated: the runtime stage still ends in `artisan serve`
  (`Dockerfile:112`), `composer run deploy`'s last line is the same server,
  and `PHP_CLI_SERVER_WORKERS=16` still governs capacity. The issue applies.
- Design:
  - `runtime` stage rebases onto the official FrankenPHP PHP 8.3 image
    (classic per-request mode; Octane/worker mode stays out per the ticket),
    installing the same gd/intl/pdo_sqlite/zip extensions. `dev` and `build`
    stages stay on `php:8.3-cli` — the bind-mount workflow is untouched.
  - A Caddyfile serves `public/` via `php_server` on plain :8000 with
    `auto_https off`; Caddy answers static assets (build bundle, the
    `public/storage` symlink) without a PHP process.
  - `composer run deploy` keeps its skeleton/migrate/seed chain; the last
    line becomes the FrankenPHP server so the Render Docker Command stays
    `composer run deploy`.
  - `USER www-data` survives; Caddy's config/data dirs get writable
    XDG paths. The `/up` healthcheck, port 8000, the storage volume, and the
    baked `public/storage` symlink all survive.
  - Forwarded scheme/IP: Caddy forwards `X-Forwarded-*` only from proxies it
    trusts, so the Caddyfile must trust the platform proxy for
    `TRUSTED_PROXIES=*` to keep meaning what the README says.
  - Regression to prove live: IMPRV-020's stream-close story
    (`terminate()`, `connection_aborted`, `ignore_user_abort`) under the
    FrankenPHP SAPI — full-lifetime and abandoned SSE curls against the
    production image.
  - `docs/backups.md` reconciliation stays with the backups tickets — this
    ticket lands first.
- 2026-08-31 — delivered. Runtime stage FROM
  `dunglas/frankenphp:1.12.7-php8.3.33-bookworm` (FrankenPHP, PHP, and
  Debian all pinned); gd/intl/pdo_sqlite/zip via `install-php-extensions`;
  `docker/Caddyfile` with `auto_https off`, `:8000`, `root *
  /var/www/src/public`, `encode zstd gzip`, `php_server`, and
  `trusted_proxies static private_ranges {$CADDY_TRUSTED_PROXIES}`;
  composer `deploy`'s last line runs `frankenphp run`; XDG config/data dirs
  chowned for `www-data`; `PHP_CLI_SERVER_WORKERS` dropped from the runtime
  env. Dev/build stages, compose stack, and Makefile untouched.
- Deviation from the design sketch: Caddy's `static` IP module rejects `*`
  as a CIDR, so its proxy trust is the separate `CADDY_TRUSTED_PROXIES`
  (space-separated CIDRs, empty ⇒ private ranges only) beside Laravel's
  `TRUSTED_PROXIES`; the README documents both.
- Live evidence against the built image: `/up` 200; storefront renders;
  `/build/assets/*.css` and `/storage/listings/*.jpg` answered with no
  `http.request` story line (statics bypass PHP); SSE full run 26.2s,
  abandon run closed with `disconnected: true`, `duration_ms: 8043` — the
  stream-close story survives the SAPI swap unchanged; 12 held streams and
  a page load answers in 0.0525s. `make check` green.
- 2026-08-31, post-resolution — the first Render deploy failed at the server
  exec: `sh: 1: frankenphp: Operation not permitted`, exit 126, after
  migrate and seed completed. Cause: the base image grants the binary
  `cap_net_bind_service=ep` (for binding 80/443), and Render's sandboxed
  runtime refuses to exec a file-capability binary; local Docker's default
  runtime grants the capability, which is why every local check passed.
  Reproduced locally with `--cap-drop=ALL` (byte-identical error). Fix:
  `setcap -r /usr/local/bin/frankenphp` in the runtime stage (the server
  binds 8000 and needs no capability), and `make run-image` now runs with
  `--cap-drop=ALL --security-opt no-new-privileges` so the local
  verification target carries Render's restrictions — the pre-fix image
  fails it, the fixed image boots, serves `/up`, `/`, and statics under it.
  Lesson recorded: local Docker's default privileges over-promise what a
  sandboxed production runtime grants; verify the production image under
  the production posture.
- Validation review: accept, no blocking defects. Its checks: boots clean
  with `CADDY_TRUSTED_PROXIES` unset; traversal and dotfile probes
  (`--path-as-is`, encoded variants, `/.env`, the sqlite files) all 404;
  Caddy's admin port stays unpublished; curl/frankenphp/php on `www-data`'s
  PATH; healthcheck reaches healthy. Its one substantive advisory —
  `docs/messaging.md` and `docs/review.md` still framing
  `PHP_CLI_SERVER_WORKERS` as the production bound — fixed in this ticket:
  both now scope the worker budget to the dev stack and name the
  production thread pool.
