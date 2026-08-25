import { fileURLToPath } from 'node:url'
import type pino from 'pino'
import { parseAsOf } from './parse-as-of.ts'
import { runWeeklyPayout } from '../actions/escrow/run-weekly-payout.ts'
import { systemClock } from '../clock.ts'
import { loadConfig } from '../config.ts'
import { openDatabase } from '../db/database.ts'
import { createCliLogger } from '../logging.ts'

/**
 * Runs the weekly payout against the configured database. The action tells the
 * story — one `payout.run` around the whole week and one `payout.pay` per
 * seller — so this entrypoint only opens the database and hands it a logger. A
 * failed run leaves `process.exitCode` at 1 rather than crashing with a raw
 * stack trace, so a scheduler sees it without one. Importable, with an
 * injectable `logger`, so a test can run it against a temp database without the
 * process ever starting.
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
    await runWeeklyPayout({ db, clock: systemClock, log, rootStory: true }, asOf)
  } catch {
    // The action already wrote the `failed` line; the exit code is what is left.
    process.exitCode = 1
  } finally {
    await db.destroy()
  }
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  await main(process.argv, process.env)
}
