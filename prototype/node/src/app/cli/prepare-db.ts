import { fileURLToPath } from 'node:url'
import type pino from 'pino'
import { main as migrateMain } from '../db/migrate.ts'
import { main as seedMain } from '../db/seed.ts'

/**
 * Migrates the configured database, then seeds it, in one process. A failing
 * migration leaves `process.exitCode` at 1 and the seed step does not run,
 * since it depends on the schema the migration would have produced.
 */
export async function main(
  argv: readonly string[],
  env: NodeJS.ProcessEnv,
  logger?: pino.Logger,
): Promise<void> {
  await migrateMain(argv, env, logger)
  if (process.exitCode === 1) return

  await seedMain(argv, env, logger)
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  await main(process.argv, process.env)
}
