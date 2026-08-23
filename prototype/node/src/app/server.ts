import { buildApp } from './app.ts'
import { systemClock } from './clock.ts'
import { loadConfig } from './config.ts'
import { openDatabase } from './db/database.ts'
import { selectMagicLinkDelivery } from './delivery/magic-link-delivery.ts'

const config = loadConfig(process.env)
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
