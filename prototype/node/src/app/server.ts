import { fileURLToPath } from 'node:url'
import type { FastifyInstance } from 'fastify'
import { buildApp } from './app.ts'
import { systemClock } from './clock.ts'
import { loadConfig } from './config.ts'
import { openDatabase } from './db/database.ts'
import { selectMagicLinkDelivery } from './delivery/magic-link-delivery.ts'

const SHUTDOWN_SIGNALS = ['SIGINT', 'SIGTERM'] as const
const FORCE_EXIT_DEADLINE_MS = 10_000

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

  armGracefulShutdown(app)

  await app.listen({ host: config.host, port: config.port })
}

/**
 * Drains in-flight requests on SIGINT/SIGTERM: flips `draining` first so
 * `/health` answers 503 immediately, then closes the app. `close()` failing
 * sets the process exit code rather than becoming an unhandled rejection,
 * and a force-exit timer fires if `close()` hangs past the deadline —
 * `unref()`'d so it never itself keeps the process alive.
 */
export function armGracefulShutdown(app: FastifyInstance): void {
  for (const signal of SHUTDOWN_SIGNALS) {
    process.once(signal, () => {
      void shutdown(app, signal)
    })
  }
}

async function shutdown(app: FastifyInstance, signal: NodeJS.Signals): Promise<void> {
  app.log.info({ signal }, 'shutdown: draining')
  app.draining = true

  const forceExit = setTimeout(() => {
    app.log.error('shutdown: forced exit after drain deadline')
    process.exit(1)
  }, FORCE_EXIT_DEADLINE_MS)
  forceExit.unref()

  try {
    await app.close()
    app.log.info('shutdown: complete')
  } catch (error) {
    app.log.error({ error }, 'shutdown: failed')
    process.exitCode = 1
  } finally {
    clearTimeout(forceExit)
  }
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  await main(process.argv, process.env)
}
