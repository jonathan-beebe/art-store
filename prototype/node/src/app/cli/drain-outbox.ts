import { parseArgs } from 'node:util'
import { fileURLToPath } from 'node:url'
import { drainOutbox } from '../actions/outbox/drain-outbox.ts'
import { systemClock } from '../clock.ts'
import { loadConfig } from '../config.ts'
import { openDatabase } from '../db/database.ts'

/**
 * Writes every pending outbox message out as an `.eml` file and prints one line
 * per file. `--dir=PATH` overrides `OUTBOX_DIR`. Importable so a test can run it
 * against a temp database without the process ever starting.
 */
export async function main(argv: readonly string[], env: NodeJS.ProcessEnv): Promise<void> {
  const config = loadConfig(env)
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
      console.log(`${message.file} ${message.recipient} ${message.subject}`)
    }
    console.log(
      drained.length === 0
        ? 'The outbox is empty.'
        : `${drained.length} message(s) written to ${outboxDir}.`,
    )
  } finally {
    await db.destroy()
  }
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  await main(process.argv, process.env)
}
