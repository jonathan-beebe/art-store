import type { Selectable } from 'kysely'
import type { Clock } from '../../clock.ts'
import { normalizeEmail } from '../../core/auth/email-address.ts'
import { isAnonymousCustomer } from '../../core/customers/customer-verification.ts'
import { planCustomerIdentity } from '../../core/customers/identity-plan.ts'
import type { AppDatabase } from '../../db/database.ts'
import type { CustomerTable } from '../../db/schema.ts'
import { toTimestamp, type Timestamp } from '../../db/timestamp.ts'
import { mergeAnonymousCustomer } from './merge-anonymous-customer.ts'

export type ClaimCustomerIdentityInput = {
  email: string
  /** The customer the identity cookie points at, if any. */
  currentCustomerId: number | null
}

/** Returns the customer that now owns the address. */
export async function claimCustomerIdentity(
  { db, clock }: { db: AppDatabase; clock: Clock },
  { email, currentCustomerId }: ClaimCustomerIdentityInput,
): Promise<Selectable<CustomerTable>> {
  const address = normalizeEmail(email)
  const verifiedAt = toTimestamp(clock.now())
  const owner = await findCustomerByEmail(db, address)

  const plan = planCustomerIdentity({
    anonymousCustomerId: await findAnonymousId(db, currentCustomerId),
    ownerOfEmailId: owner?.id ?? null,
  })

  switch (plan.action) {
    case 'createVerified':
      return await createVerifiedCustomer(db, address, verifiedAt)
    case 'claimAnonymous':
      return await claimAddress(db, plan.anonymousCustomerId, address, verifiedAt)
    case 'signInExisting':
      return await settleVerification(db, plan.verifiedCustomerId, verifiedAt)
    case 'mergeAnonymousInto': {
      const verified = await settleVerification(db, plan.verifiedCustomerId, verifiedAt)

      await mergeAnonymousCustomer(
        { db, clock },
        {
          anonymousCustomerId: plan.anonymousCustomerId,
          verifiedCustomerId: verified.id,
        },
      )

      return verified
    }
  }
}

async function findCustomerByEmail(
  db: AppDatabase,
  address: string,
): Promise<Selectable<CustomerTable> | undefined> {
  return await db
    .selectFrom('customers')
    .selectAll()
    .where('email', '=', address)
    .executeTakeFirst()
}

async function findAnonymousId(
  db: AppDatabase,
  currentCustomerId: number | null,
): Promise<number | null> {
  if (currentCustomerId === null) return null

  const current = await db
    .selectFrom('customers')
    .selectAll()
    .where('id', '=', currentCustomerId)
    .executeTakeFirst()

  return current !== undefined && isAnonymousCustomer(current) ? current.id : null
}

async function createVerifiedCustomer(
  db: AppDatabase,
  address: string,
  verifiedAt: Timestamp,
): Promise<Selectable<CustomerTable>> {
  return await db
    .insertInto('customers')
    .values({ email: address, name: null, emailVerifiedAt: verifiedAt, createdAt: verifiedAt })
    .returningAll()
    .executeTakeFirstOrThrow()
}

async function claimAddress(
  db: AppDatabase,
  customerId: number,
  address: string,
  verifiedAt: Timestamp,
): Promise<Selectable<CustomerTable>> {
  await db
    .updateTable('customers')
    .set({ email: address, emailVerifiedAt: verifiedAt })
    .where('id', '=', customerId)
    .execute()

  return await readCustomer(db, customerId)
}

/**
 * A guest checkout can leave an address on a customer without verifying it, so
 * a link for that address settles it and leaves an earlier one alone.
 */
async function settleVerification(
  db: AppDatabase,
  customerId: number,
  verifiedAt: Timestamp,
): Promise<Selectable<CustomerTable>> {
  await db
    .updateTable('customers')
    .set({ emailVerifiedAt: verifiedAt })
    .where('id', '=', customerId)
    .where('emailVerifiedAt', 'is', null)
    .execute()

  return await readCustomer(db, customerId)
}

async function readCustomer(
  db: AppDatabase,
  customerId: number,
): Promise<Selectable<CustomerTable>> {
  return await db
    .selectFrom('customers')
    .selectAll()
    .where('id', '=', customerId)
    .executeTakeFirstOrThrow()
}
