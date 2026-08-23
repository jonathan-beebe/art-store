import type { Selectable } from 'kysely'
import { normalizeEmail } from '../../core/auth/email-address.ts'
import type { AdminTable } from '../../db/schema.ts'
import type { ActionContext } from '../action-context.ts'

/**
 * Admin rows are seeded, never created by signing in, so an address with no row
 * gets no link and no session. Returns null for every other address.
 */
export async function findAdminByEmail(
  { db }: Pick<ActionContext, 'db'>,
  email: string,
): Promise<Selectable<AdminTable> | null> {
  const admin = await db
    .selectFrom('admins')
    .selectAll()
    .where('email', '=', normalizeEmail(email))
    .executeTakeFirst()

  return admin ?? null
}
