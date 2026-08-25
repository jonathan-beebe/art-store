import { fileURLToPath } from 'node:url'
import type { FastifyInstance } from 'fastify'
import { buildApp } from './app.ts'
import { systemClock } from './clock.ts'
import { loadConfig } from './config.ts'
import { openDatabase } from './db/database.ts'
import { selectMagicLinkDelivery } from './delivery/magic-link-delivery.ts'
import { tellStory } from './log-story.ts'
import { prefixedMsg } from './core/logging/story-emoji.ts'

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

  await tellStory(
    app.log,
    {
      event: 'app.boot',
      will: {
        msg: `starting on ${config.host}:${config.port}`,
        data: { host: config.host, port: config.port, environment: config.environment },
      },
      ended: (address) => ({ phase: 'did', msg: `listening on ${address}`, data: { address } }),
    },
    () => app.listen({ host: config.host, port: config.port }),
  )
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
  const forceExit = setTimeout(() => {
    logDrainDeadline(app)
    process.exit(1)
  }, FORCE_EXIT_DEADLINE_MS)
  forceExit.unref()

  try {
    await tellStory(
      app.log,
      {
        event: 'app.shutdown',
        will: { msg: `draining in-flight requests on ${signal}`, data: { signal } },
        ended: () => ({ phase: 'did', msg: 'closed cleanly', data: { signal } }),
      },
      async () => {
        app.draining = true
        await app.close()
      },
    )
  } catch {
    // tellStory already wrote the `failed` line; the exit code is what is left.
    process.exitCode = 1
  } finally {
    clearTimeout(forceExit)
  }
}

function logDrainDeadline(app: FastifyInstance): void {
  app.log.error(
    {
      event: 'app.shutdown',
      phase: 'failed',
      error: {
        type: 'DrainDeadline',
        message: `in-flight requests did not finish within ${FORCE_EXIT_DEADLINE_MS}ms`,
      },
    },
    prefixedMsg('forcing exit after the drain deadline', 'failed', 'error', false),
  )
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  await main(process.argv, process.env)
}
