import { tmpdir } from 'node:os'
import { fileURLToPath } from 'node:url'
import { buildApp } from '../app.ts'
import { systemClock } from '../clock.ts'
import { loadConfig } from '../config.ts'
import { IN_MEMORY_DATABASE, openDatabase } from '../db/database.ts'
import { flashMagicLinkDelivery } from '../delivery/flash-magic-link-delivery.ts'

/**
 * Enough deployment to boot. The database is in memory and nothing is served,
 * so the report is the code's shape rather than any environment's.
 */
function reportConfig(): ReturnType<typeof loadConfig> {
  return loadConfig({
    DATABASE_FILE: IN_MEMORY_DATABASE,
    LOG_LEVEL: 'silent',
    // The static plugin refuses a root that does not exist, and no upload is
    // read here.
    UPLOADS_DIR: tmpdir(),
  })
}

/**
 * Fastify's own account of what this app is: every route it will answer, then
 * the plugin tree those routes hang in. Both come from the framework's
 * introspection, so neither can drift from the code the way a hand-kept table
 * does.
 */
export async function routeReport(): Promise<string> {
  const db = openDatabase(IN_MEMORY_DATABASE)
  const app = buildApp({
    db,
    clock: systemClock,
    config: reportConfig(),
    magicLinkDelivery: flashMagicLinkDelivery,
  })

  try {
    await app.ready()

    return `Routes\n${app.printRoutes({ commonPrefix: false })}\nPlugins\n${app.printPlugins()}\n`
  } finally {
    await app.close()
    await db.destroy()
  }
}

/** Writes the report. Importable, with the stream injected, so a test reads it. */
export async function main(out: NodeJS.WritableStream = process.stdout): Promise<void> {
  out.write(await routeReport())
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  await main()
}
