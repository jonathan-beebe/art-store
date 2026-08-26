import { fileURLToPath } from 'node:url'
import type pino from 'pino'
import { parseAsOf } from './parse-as-of.ts'
import { sweepStaleOrders } from '../actions/orders/sweep-stale-orders.ts'
import { pruneRateLimitWindows } from '../actions/rate-limit/prune-rate-limit-windows.ts'
import { systemClock } from '../clock.ts'
import { loadConfig, type AppConfig } from '../config.ts'
import { openDatabase } from '../db/database.ts'
import { pruneLogLines } from '../log-store.ts'
import { createCliLogger, defaultLogStore } from '../logging.ts'

const DAY_MS = 24 * 60 * 60 * 1000

/**
 * Cancels every order left unverified longer than `STALE_ORDER_HOURS`, so the
 * stock a visitor claimed and walked away from goes back on the storefront,
 * then prunes the `rate_limit_windows` rows no configured limit can still
 * read and the stored log lines older than `LOG_RETENTION_DAYS`.
 * `--as-of=YYYY-MM-DD` runs all three as though the run happened then. The
 * sweep tells its story; the prunes are silent. A
 * failed run leaves `process.exitCode` at 1 rather than crashing with a raw
 * stack trace. Importable, with an injectable `logger`, so a test can run it
 * against a temp database without the process ever starting.
 */
export async function main(
  argv: readonly string[],
  env: NodeJS.ProcessEnv,
  logger?: pino.Logger,
): Promise<void> {
  const config = loadConfig(env)
  const log = logger ?? createCliLogger(config)
  const asOf = parseAsOf(argv.slice(2), systemClock.now())
  const db = openDatabase(config.databaseFile)

  try {
    await sweepStaleOrders(
      { db, clock: systemClock, log, rootStory: true },
      { staleHours: config.staleOrderHours, asOf },
    )
    await pruneRateLimitWindows({ db }, { limits: Object.values(config.rateLimits), asOf })
    pruneLogHistory(config, asOf)
  } catch {
    // A failed sweep already wrote its `failed` line; a failed prune is silent
    // by design. Either way the exit code carries the failure.
    process.exitCode = 1
  } finally {
    await db.destroy()
  }
}

/**
 * The log-line half of the housekeeping: lines older than `LOG_RETENTION_DAYS`
 * before `asOf` are deleted. `createCliLogger` above already opened the
 * per-file store, so this prunes over the same handle, whose final flush and
 * close belong to the process exit — nothing here to destroy. Skipped when
 * retention or the store is `off`.
 */
function pruneLogHistory(config: AppConfig, asOf: Date): void {
  if (config.logRetentionDays === 'off') return

  const store = defaultLogStore(config)
  if (store === undefined) return

  pruneLogLines(store, new Date(asOf.getTime() - config.logRetentionDays * DAY_MS))
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  await main(process.argv, process.env)
}
