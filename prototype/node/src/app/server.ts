import { fileURLToPath } from 'node:url'
import { buildApp } from './app.ts'
import { systemClock } from './clock.ts'
import { loadConfig } from './config.ts'
import { openDatabase } from './db/database.ts'
import { selectMagicLinkDelivery } from './delivery/magic-link-delivery.ts'

/** Builds and listens on the configured host and port. Importable so a test
 * can call it against a temp database without the process ever starting. */
export async function main(_argv: readonly string[], env: NodeJS.ProcessEnv): Promise<void> {
  const config = loadConfig(env)
  const db = openDatabase(config.databaseFile)
  const app = buildApp({
    db,
    clock: systemClock,
    config,
    magicLinkDelivery: selectMagicLinkDelivery(config.magicLinkDelivery),
  })

  app.addHook('onClose', async () => {
    await db.destroy()
  })

  for (const signal of ['SIGINT', 'SIGTERM'] as const) {
    process.once(signal, () => {
      void app.close()
    })
  }

  await app.listen({ host: config.host, port: config.port })
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  await main(process.argv, process.env)
}
