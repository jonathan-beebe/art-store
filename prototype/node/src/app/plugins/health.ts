import type { FastifyInstance } from 'fastify'
import { sql } from 'kysely'
import { evaluateHealth, healthStatusCode, type HealthChecks } from '../core/health/health-status.ts'
import type { AppDatabase } from '../db/database.ts'
import { pendingMigrations } from '../db/migrator.ts'

/**
 * `/health` is the one thing an orchestrator polls before routing traffic
 * here, so it is registered at the root — outside every site's guard —
 * and answers JSON, which keeps it out of the page-view rollup for free
 * (that hook only counts HTML responses).
 */
export function addHealth(app: FastifyInstance): void {
  app.get('/health', async (_request, reply) => {
    const checks = await runChecks(app.db)
    const status = evaluateHealth(checks, app.draining)

    reply.code(healthStatusCode(status))

    return { status, checks, uptimeSeconds: Math.floor(process.uptime()) }
  })
}

async function runChecks(db: AppDatabase): Promise<HealthChecks> {
  const [database, pending] = await Promise.all([pingDatabase(db), pendingMigrations(db)])

  return { database, migrations: pending.length === 0 ? 'current' : 'pending' }
}

async function pingDatabase(db: AppDatabase): Promise<'ok' | 'failed'> {
  try {
    await sql`select 1`.execute(db)
    return 'ok'
  } catch {
    return 'failed'
  }
}
