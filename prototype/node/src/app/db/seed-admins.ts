import type { Clock } from '../clock.ts'
import { newId } from '../ids.ts'
import type { AppDatabase } from './database.ts'
import { toTimestamp } from './timestamp.ts'

/**
 * The platform operators. Admin rows are never created by signing in, so this
 * list is the whole of who can reach `/admin`.
 */
export const SEEDED_ADMINS: readonly { email: string; name: string }[] = [
  { email: 'jonathan-beebe@outlook.com', name: 'Jonathan Beebe' },
  { email: 'annaschmunk@pm.me', name: 'Anna Schmunk' },
]

/** Adds any operator the database is missing and returns how many it added. */
export async function seedAdmins({
  db,
  clock,
}: {
  db: AppDatabase
  clock: Clock
}): Promise<number> {
  const createdAt = toTimestamp(clock.now())

  const result = await db
    .insertInto('admins')
    .values(SEEDED_ADMINS.map((admin) => ({ id: newId('adm', clock.now()), ...admin, createdAt })))
    .onConflict((conflict) => conflict.column('email').doNothing())
    .executeTakeFirst()

  return Number(result.numInsertedOrUpdatedRows ?? 0n)
}
