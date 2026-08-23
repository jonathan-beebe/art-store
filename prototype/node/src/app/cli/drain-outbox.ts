import { parseArgs } from 'node:util'
import { fileURLToPath } from 'node:url'
import type pino from 'pino'
import { drainOutbox } from '../actions/outbox/drain-outbox.ts'
import { systemClock } from '../clock.ts'
import { loadConfig } from '../config.ts'
import { openDatabase } from '../db/database.ts'
import { createCliLogger } from '../logging.ts'

/**
 * Writes every pending outbox message out as an `.eml` file and logs one
 * structured line per file plus a summary. `--dir=PATH` overrides
 * `OUTBOX_DIR`. A failure is logged and leaves `process.exitCode` at 1 rather
 * than crashing with a raw stack trace. Importable, with an injectable
 * `logger`, so a test can run it against a temp database without the process
 * ever starting.
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
    const drained = await drainOutbox({ db, clock: systemClock }, { outboxDir })

    for (const message of drained) {
      log.info(
        { event: 'outbox.drained', id: message.id, recipient: message.recipient, file: message.file },
        `${message.file} ${message.recipient} ${message.subject}`,
      )
    }
    log.info(
      { event: 'outbox.drain_run', count: drained.length, outboxDir },
      drained.length === 0 ? 'the outbox is empty' : `${drained.length} message(s) written to ${outboxDir}`,
    )
  } catch (error) {
    log.error({ err: error }, 'the outbox drain failed')
    process.exitCode = 1
  } finally {
    await db.destroy()
  }
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  await main(process.argv, process.env)
}
