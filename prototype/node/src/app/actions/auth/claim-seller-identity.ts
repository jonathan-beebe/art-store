import type { Selectable } from 'kysely'
import { normalizeEmail } from '../../core/auth/email-address.ts'
import type { AppDatabase } from '../../db/database.ts'
import type { SellerTable } from '../../db/schema.ts'
import { toTimestamp, type Timestamp } from '../../db/timestamp.ts'
import type { ActionContext } from '../action-context.ts'
import { runInTransaction } from '../transaction.ts'

/**
 * A verified link is the whole of seller sign-up: the first one for an address
 * creates the account, and every later one returns the account already there.
 * The read and the write it decides share one transaction, so two links for one
 * address cannot both find nothing and both insert.
 */
export async function claimSellerIdentity(
  context: ActionContext,
  email: string,
): Promise<Selectable<SellerTable>> {
  const address = normalizeEmail(email)

  return runInTransaction(context, async ({ db, clock }) => {
    const verifiedAt = toTimestamp(clock.now())
    const existing = await db
      .selectFrom('sellers')
      .selectAll()
      .where('email', '=', address)
      .executeTakeFirst()

    if (existing === undefined) return await createSeller(db, address, verifiedAt)
    if (existing.emailVerifiedAt !== null) return existing

    return await settleVerification(db, existing.id, verifiedAt)
  })
}

async function createSeller(
  db: AppDatabase,
  address: string,
  verifiedAt: Timestamp,
): Promise<Selectable<SellerTable>> {
  return await db
    .insertInto('sellers')
    .values({
      email: address,
      name: null,
      shopName: null,
      emailVerifiedAt: verifiedAt,
      createdAt: verifiedAt,
    })
    .returningAll()
    .executeTakeFirstOrThrow()
}

/** Returns the row the update leaves behind rather than one rebuilt in memory. */
async function settleVerification(
  db: AppDatabase,
  sellerId: number,
  verifiedAt: Timestamp,
): Promise<Selectable<SellerTable>> {
  return await db
    .updateTable('sellers')
    .set({ emailVerifiedAt: verifiedAt })
    .where('id', '=', sellerId)
    .returningAll()
    .executeTakeFirstOrThrow()
}
