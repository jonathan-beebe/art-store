import { parseArgs } from 'node:util'
import { fileURLToPath } from 'node:url'
import type pino from 'pino'
import { drainOutbox } from '../actions/outbox/drain-outbox.ts'
import { systemClock } from '../clock.ts'
import { loadConfig } from '../config.ts'
import { openDatabase } from '../db/database.ts'
import { createCliLogger } from '../logging.ts'
import { tellStory } from '../log-story.ts'

/**
 * Writes every pending outbox message out as an `.eml` file: one `doing` line
 * per file inside a `notification.deliver` run. `--dir=PATH` overrides
 * `OUTBOX_DIR`. A failure leaves `process.exitCode` at 1 rather than crashing
 * with a raw stack trace. Importable, with an injectable `logger`, so a test can
 * run it against a temp database without the process ever starting.
 */
export async function main(
  argv: readonly string[],
  env: NodeJS.ProcessEnv,
  logger?: pino.Logger,
): Promise<void> {
  const config = loadConfig(env)
  const log = logger ?? createCliLogger(config)
  const { values } = parseArgs({
    args: argv.slice(2),
    options: { dir: { type: 'string' } },
    strict: true,
  })
  const outboxDir = values.dir ?? config.outboxDir
  const db = openDatabase(config.databaseFile)

  try {
    await tellStory(
      log,
      {
        event: 'notification.deliver',
        root: true,
        will: { msg: `draining the outbox into ${outboxDir}`, data: { outbox_dir: outboxDir } },
        ended: (drained) => ({
          phase: 'did',
          msg:
            drained.length === 0
              ? 'the outbox is empty'
              : `${drained.length} message(s) written to ${outboxDir}`,
          data: { outbox_dir: outboxDir, count: drained.length },
        }),
      },
      () => drainOutbox({ db, clock: systemClock, log }, { outboxDir }),
    )
  } catch {
    // tellStory already wrote the `failed` line; the exit code is what is left.
    process.exitCode = 1
  } finally {
    await db.destroy()
  }
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  await main(process.argv, process.env)
}
