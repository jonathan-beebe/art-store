import type { Selectable } from 'kysely'
import type { ActorType } from '../../core/auth/actor-type.ts'
import { magicLinkStatus } from '../../core/auth/magic-link-status.ts'
import { digestMagicLinkToken } from '../../core/auth/magic-link-token.ts'
import type { ActorId, CustomerId, MagicLinkId } from '../../core/ids/entity-ids.ts'
import { claimCustomerIdentity } from '../customers/claim-customer-identity.ts'
import type { AppDatabase } from '../../db/database.ts'
import type { MagicLinkTable } from '../../db/schema.ts'
import { fromNullableTimestamp, fromTimestamp, toTimestamp } from '../../db/timestamp.ts'
import type { ActionContext } from '../action-context.ts'
import { runInTransaction } from '../transaction.ts'
import { claimSellerIdentity } from './claim-seller-identity.ts'
import { findAdminByEmail } from './find-admin-by-email.ts'

export type MagicLinkRefusal = 'expired' | 'consumed' | 'unrecognized'

export type MagicLinkSignIn =
  | { outcome: 'unknown' }
  | { outcome: 'refused'; actorType: ActorType; refusal: MagicLinkRefusal }
  | { outcome: 'signedIn'; actorType: ActorType; actorId: ActorId; redirectTo: string | null }

export type SignInWithMagicLinkInput = {
  token: string
  /** The customer the identity cookie points at; only a customer link reads it. */
  currentCustomerId: CustomerId | null
}

/**
 * Spends one link and returns who it signed in. Spending the link and claiming
 * the actor share one transaction, so a claim that fails hands the link back
 * unspent. Every refusal names the side of the marketplace that asked, so the
 * caller knows which sign-in page to send the visitor back to.
 */
export async function signInWithMagicLink(
  context: ActionContext,
  { token, currentCustomerId }: SignInWithMagicLinkInput,
): Promise<MagicLinkSignIn> {
  return runInTransaction(context, async (transacted) => {
    const { db, clock } = transacted
    const link = await db
      .selectFrom('magicLinks')
      .selectAll()
      .where('tokenDigest', '=', digestMagicLinkToken(token))
      .executeTakeFirst()

    if (link === undefined) return { outcome: 'unknown' }

    const now = clock.now()
    const status = magicLinkStatus(
      {
        expiresAt: fromTimestamp(link.expiresAt),
        consumedAt: fromNullableTimestamp(link.consumedAt),
      },
      now,
    )

    if (status !== 'usable') {
      return { outcome: 'refused', actorType: link.actorType, refusal: status }
    }

    if (!(await consume(db, link.id, now))) {
      return { outcome: 'refused', actorType: link.actorType, refusal: 'consumed' }
    }

    return await signInAs(transacted, link, currentCustomerId)
  })
}

async function signInAs(
  context: ActionContext,
  link: Selectable<MagicLinkTable>,
  currentCustomerId: CustomerId | null,
): Promise<MagicLinkSignIn> {
  const actorId = await claimActor(context, link, currentCustomerId)

  if (actorId === null) {
    return { outcome: 'refused', actorType: link.actorType, refusal: 'unrecognized' }
  }

  return {
    outcome: 'signedIn',
    actorType: link.actorType,
    actorId,
    redirectTo: link.redirectTo,
  }
}

/**
 * Spends the link, reporting whether this request is the one that spent it. The
 * `consumed_at is null` clause is what makes a link work exactly once when two
 * requests arrive together.
 */
async function consume(db: AppDatabase, linkId: MagicLinkId, now: Date): Promise<boolean> {
  const result = await db
    .updateTable('magicLinks')
    .set({ consumedAt: toTimestamp(now) })
    .where('id', '=', linkId)
    .where('consumedAt', 'is', null)
    .executeTakeFirst()

  return result.numUpdatedRows > 0n
}

/** Returns null when the link names an actor the application will not create. */
async function claimActor(
  context: ActionContext,
  link: Selectable<MagicLinkTable>,
  currentCustomerId: CustomerId | null,
): Promise<ActorId | null> {
  switch (link.actorType) {
    case 'seller':
      return (await claimSellerIdentity(context, link.email)).id
    case 'customer':
      return (await claimCustomerIdentity(context, { email: link.email, currentCustomerId })).id
    case 'admin':
      return (await findAdminByEmail(context, link.email))?.id ?? null
  }
}
