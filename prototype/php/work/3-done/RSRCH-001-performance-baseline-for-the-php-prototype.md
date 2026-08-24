---
id: RSRCH-001
type: research
status: resolved
created: 2026-08-24
---

# RSRCH-001: Performance baseline for the PHP prototype

## Problem
The prototype is reported to bog down the machine it runs on. Nothing in
`prototype/php` records what it costs to start, to sit idle, or to answer a
page, so there is no number to aim a fix at and no way to tell a fix from a
coincidence.

## Goal
A recorded baseline — startup, idle CPU, request latency, CPU per request,
query counts per page — taken with commands anyone can re-run, so every
later performance ticket validates against the same measurement.

## Outcome
This ticket holds the numbers and the commands. Each performance ticket
filed after it names one of these metrics in its own Outcome and re-runs the
command that produced it.

## Why it matters
Three of the five suspects this baseline was taken to check turned out to
cost nothing measurable. Without the measurement the work would have gone to
the wrong places.

## Discovery notes
Measured 2026-08-24 on macOS (12 cores, Docker Desktop), branch `perf/php`
at 218a323, `COMPOSE_PROJECT_NAME=perf-php-php`, port 8000, seeded demo data
(`make fresh`).

## Related work
- FEAT-016 (live unread badge over SSE)
- FEAT-019 (structured JSON logs), FEAT-021 (rate limits), FEAT-023 (page-view roll-up)
- MAINT-003 (make vocabulary, check gate)

## Working

### Measurement commands

All run from `prototype/php` with `COMPOSE_PROJECT_NAME` exported by `make`.
The stack must be up (`make up`) for everything but the startup numbers.

**M1 — cold start** (no `src/vendor`, no `src/node_modules`, image built):

```
make down; rm -rf src/vendor src/node_modules src/public/build
t0=$(date +%s); docker compose up -d
until curl -s -o /dev/null -m 2 http://localhost:8000/up; do :; done
echo "cold: $(( $(date +%s) - t0 ))s"
```

**M2 — warm restart** (dependencies present):

```
make down
t0=$(python3 -c 'import time;print(time.time())'); docker compose up -d
until curl -s -o /dev/null -m 2 http://localhost:8000/up; do :; done
python3 -c "import time;print('warm: %.2fs' % (time.time()-$t0))"
```

**M3 — entrypoint phase cost.** Wall time of one phase run through
`docker compose run`, minus the 1.31 s a no-op `docker compose run` costs:

```
/usr/bin/time -p docker compose run --rm --no-deps --entrypoint sh app \
  -c 'cd /var/www/src && npm run build >/dev/null 2>&1'
/usr/bin/time -p docker compose run --rm --no-deps --entrypoint sh app -c 'true'
```

**M4 — idle CPU and memory:**

```
docker stats --no-stream --format '{{.CPUPerc}} {{.MemUsage}}' $(docker compose ps -q app)
```

**M5 — CPU consumed per request.** Reads the container cgroup's own CPU
accounting either side of a fixed batch, so it measures the server rather
than curl:

```
C=$(docker compose ps -q app)
usec() { docker exec $C awk '/^usage_usec/{print $2}' /sys/fs/cgroup/cpu.stat; }
A=$(usec); for i in $(seq 1 60); do curl -s -o /dev/null http://localhost:8000/; done; B=$(usec)
python3 -c "print('%.1f ms cpu/req' % (($B-$A)/1000.0/60))"
```

Noisy on a loaded machine. Take the minimum of four alternating rounds, not
the mean.

**M6 — request latency.** 30 samples of `%{time_total}`, reported as p50.

**M7 — queries per request.** Boots the HTTP kernel, hooks `DB::listen`, and
dispatches a request carrying a returning visitor's `customer_id` cookie:

```
docker compose run --rm --no-deps -T --entrypoint php app artisan tinker <<'PHP'
$qs = [];
\Illuminate\Support\Facades\DB::listen(function ($q) use (&$qs) { $qs[] = $q->sql; });
$cid = \App\Models\Customer::query()->value('id');
$enc = app('encrypter');
$cookie = $enc->encrypt(\Illuminate\Cookie\CookieValuePrefix::create('customer_id', $enc->getKey()).$cid, false);
foreach (['/', '/cart', '/art/'.\App\Models\Listing::query()->value('slug'), '/favorites'] as $p) {
    $qs = [];
    $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
    $req = \Illuminate\Http\Request::create($p, 'GET');
    $req->cookies->set('customer_id', $cookie);
    $res = $kernel->handle($req);
    $kernel->terminate($req, $res);
    echo 'QCOUNT ', str_pad($p, 28), ' status=', $res->getStatusCode(), ' queries=', count($qs), PHP_EOL;
}
PHP
```

Registering the listener once and clearing `$qs` per path matters: a listener
per iteration multiplies every later count.

**M8 — stream occupancy.** Opens N event streams, kills the clients, then
times a page load:

```
for i in $(seq 1 12); do curl -s -N -m 3 -o /dev/null http://localhost:8000/events & done
sleep 4; jobs -p | xargs kill 2>/dev/null
curl -s -o /dev/null -w '%{time_total}s\n' -m 90 http://localhost:8000/
```

### Baseline numbers

**Startup**

| Metric | Value |
|---|---|
| M1 cold start to first `200 /up` | 44 s |
| — `composer install` | 31 s |
| — `npm install` | 9 s |
| — `vite build` | 1.3 s |
| — `artisan migrate` | 0.2 s |
| M2 warm restart to first `200 /up` | 3.88 s |
| M3 `npm run build` | 4.38 s (5.69 s wall − 1.31 s run overhead) |
| M3 `artisan migrate --force` | 1.58 s |
| M3 `artisan storage:link --force` | 1.04 s |

The entrypoint runs on every `docker compose run`, not only on `up`, so
every `make test`, `make shell`, `make seed`, `make routes` pays M3 in full.
`make check` is `lint assets test`: `lint` bypasses the entrypoint
(`--no-deps --entrypoint composer`), `assets` pays it and then builds again
as its own command, `test` pays it once more — **three Vite builds per gate
run**.

**Idle**

| Metric | Value |
|---|---|
| M4 CPU, no streams | 0.06 – 0.22 % |
| M4 memory | 101.8 MiB |
| Container CPU over 30 s, 0 streams | 1.4 % of one core |
| Container CPU over 30 s, 1 stream | 4.5 % of one core |
| Container CPU over 30 s, 3 streams | 5.6 % of one core |

`php artisan serve` runs one master plus five workers
(`PHP_CLI_SERVER_WORKERS=5`).

**Request latency (M6, p50, host curl, seeded data)**

| Page | p50 | min |
|---|---|---|
| `/up` | 17 ms | 12 ms |
| `/` (storefront index) | 39 ms | 26 ms |
| `/cart` | 26 ms | 21 ms |
| `/login` | 20 ms | 16 ms |
| `/admin` (signed in) | 75 ms | — |
| `/admin/stats` | 32 ms | — |
| `/admin/accounting` | 36 ms | — |
| `/admin/ledger` | 60 ms | — |

**CPU per request (M5, minimum of four rounds)**

| Page | ms CPU/req |
|---|---|
| `/` | 24.7 |
| `/login` | 12.9 |

**Queries per request (M7, returning visitor)**

| Page | queries |
|---|---|
| `/` | 16 |
| `/cart` | 13 |
| `/art/{slug}` | 18 |
| `/favorites` | 13 |

A first-ever visitor's `GET /` is 14 queries including four writes: the
`customers` insert, the `carts` insert, the `sessions` insert, and the
`page_view_counts` upsert.

**Stream occupancy (M8)**

| Concurrent streams | `GET /` |
|---|---|
| 5 | 0.06 – 0.12 s |
| 8 | 0.11 – 0.22 s |
| 12 | **50.8 s** |
| 12, clients killed after 3 s | **49.4 s** |

### What the measurements refuted

- **Startup is not the cost.** A warm restart is 3.9 s. The 44 s cold start
  is `composer install` and `npm install`, paid once per checkout, and
  neither is work this repository can skip.
- **Idle CPU is not the cost.** 0.1 % with nothing open, 5.6 % of one core
  with three live badge streams. The badge's 2-second tick is a `count`
  query, not a spin.
- **The rate limiter never fires on a page load.** `RateLimitGate` is
  reached only from POST handlers and the magic-link GET. A plain `GET /`
  touches the `cache` table zero times.
- **OPcache buys nothing here.** `opcache.enable_cli=1` with timestamp
  validation, measured over four alternating rounds, left CPU per request
  unchanged (24.8 / 24.7 / 27.6 / 30.9 ms against a 24.7 / 24.9 / 26.9 /
  30.2 ms baseline), and made it worse when combined with the pcov change.
  Not filed.
  One trap worth recording: `artisan serve` execs `php -S` as a **child**
  process and `ServeCommand::serverCommand()` passes it no `-d` flags, so
  `php -d opcache.enable_cli=1 artisan serve` changes nothing about the
  server that answers requests. An ini file in `conf.d` is the only way to
  reach it. A first measurement taken the `-d` way showed a spurious 15 %
  and was discarded.
- **Admin pages are cheap at demo scale.** `/admin` at 75 ms and
  `/admin/accounting` at 36 ms. `Fulfillment::platformFees()` hydrating the
  whole `fulfillments` table without an aggregate is a scaling defect, not a
  cost anyone feels today.

### What the measurements found

Filed as tickets:

- **BUG-004** — `make check` is red on any checkout whose `.env` came from
  `.env.example`.
- **IMPRV-006** — a closed browser tab keeps holding a `serve` worker, so a
  page load stalls for tens of seconds (M8).
- **IMPRV-007** — pcov instruments every request in the dev server: 30–33 %
  of the CPU of every page (M5).
- **IMPRV-008** — the entrypoint rebuilds the Vite bundle on every container
  start and every `docker compose run` (M2, M3).
- **IMPRV-009** — the customer identity is resolved twice per storefront
  request (M7).

Observed and deliberately not filed, for want of a cost anyone can measure
at demo scale: the missing index on `carts.customer_id`; the per-cart-item
`hasActiveRemoval()` query in `Cart::placementPlan()`;
`Fulfillment::platformFees()`; the unpaginated `ledger_entries` and
`favorites` reads.

### The branch's numbers after the five tickets

Same host, same commands, seeded demo data, `perf/php` at IMPRV-009.

| metric | baseline | after | ticket |
|---|---|---|---|
| M2 warm restart to first `200 /up` | 3.88 s | **1.81 – 2.05 s** | IMPRV-008 |
| `make check` wall | 104.4 s | **91.4 – 102.9 s** | IMPRV-008 |
| Vite builds per `make check` | 3 | **1** | IMPRV-008 |
| M5 CPU per `GET /` (min of 4 rounds) | 24.7 ms | **16.6 ms** | IMPRV-007 |
| M6 `GET /` p50 | 39 ms | **25 ms** | IMPRV-007 |
| M6 `GET /cart` p50 | 26 ms | **23 ms** | IMPRV-007 |
| M6 `GET /login` p50 | 20 ms | **14 ms** | IMPRV-007 |
| M7 queries, `/` | 16 | **14** | IMPRV-009 |
| M7 queries, `/cart` | 13 | **11** | IMPRV-009 |
| M7 queries, `/art/{slug}` | 18 | **16** | IMPRV-009 |
| M7 queries, `/favorites` | 13 | **11** | IMPRV-009 |
| M8 12 streams held, then `GET /` | 50.8 s | **0.06 s** | IMPRV-006 |
| M8 12 streams abandoned, then `GET /` | 49.4 s | **0.06 s** | IMPRV-006 |
| M4 CPU, 3 live streams over 30 s | 5.6 % of one core | **1.6 % of one core** | IMPRV-006 |
| M4 memory | 101.8 MiB | 128.7 MiB | IMPRV-006 (5 → 16 workers) |

Memory is the one number that moved the wrong way, and it is the price of the
worker pool that answers a page while streams are open.

The cold start is unchanged at ~44 s: `composer install` and `npm install` are
the whole of it, they run once per checkout, and neither is work this
repository can skip.
