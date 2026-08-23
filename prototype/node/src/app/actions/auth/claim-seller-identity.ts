import type { Selectable } from 'kysely'
import type { Clock } from '../../clock.ts'
import { normalizeEmail } from '../../core/auth/email-address.ts'
import type { AppDatabase } from '../../db/database.ts'
import type { SellerTable } from '../../db/schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

export type ClaimSellerIdentityDependencies = {
  db: AppDatabase
  clock: Clock
}

/**
 * A verified link is the whole of seller sign-up: the first one for an address
 * creates the account, and every later one returns the account already there.
 */
export async function claimSellerIdentity(
  { db, clock }: ClaimSellerIdentityDependencies,
  email: string,
): Promise<Selectable<SellerTable>> {
  const address = normalizeEmail(email)
  const verifiedAt = toTimestamp(clock.now())

  const existing = await db
    .selectFrom('sellers')
    .selectAll()
    .where('email', '=', address)
    .executeTakeFirst()

  if (existing !== undefined) {
    if (existing.emailVerifiedAt !== null) return existing

    await db
      .updateTable('sellers')
      .set({ emailVerifiedAt: verifiedAt })
      .where('id', '=', existing.id)
      .execute()

    return { ...existing, emailVerifiedAt: verifiedAt }
  }

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
