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
import { actionStory } from '../action-story.ts'
import { refused, type Refusal } from '../../core/refusal.ts'
import { claimSellerIdentity } from './claim-seller-identity.ts'
import { findAdminByEmail } from './find-admin-by-email.ts'

/** The sub-category a spent link was refused under, beside `unknown_token`
 * for a token no link was ever issued for. */
export type MagicLinkRefusal = 'expired' | 'consumed' | 'unrecognized'

export type MagicLinkSignIn =
  | { outcome: 'signedIn'; actorType: ActorType; actorId: ActorId; redirectTo: string | null }
  | Refusal<'unknown_token'>
  | Refusal<MagicLinkRefusal, { actor_type: ActorType }>

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
  return actionStory<MagicLinkSignIn>(
    context,
    {
      event: 'magic_link.consume',
      will: { msg: 'spending a sign-in link' },
      refusedMsg: 'the sign-in link cannot be spent',
      ended: (signIn) => ({
        phase: 'did',
        msg: `signed in a ${signIn.actorType}`,
        data: { actor_type: signIn.actorType, actor_id: signIn.actorId },
      }),
    },
    async (transacted) => {
      const { db, clock } = transacted
      const link = await db
        .selectFrom('magicLinks')
        .selectAll()
        .where('tokenDigest', '=', digestMagicLinkToken(token))
        .executeTakeFirst()

      if (link === undefined) return refused('unknown_token')

      const now = clock.now()
      const status = magicLinkStatus(
        {
          expiresAt: fromTimestamp(link.expiresAt),
          consumedAt: fromNullableTimestamp(link.consumedAt),
        },
        now,
      )

      if (status !== 'usable') {
        return refused(status, { actor_type: link.actorType })
      }

      if (!(await consume(db, link.id, now))) {
        return refused('consumed', { actor_type: link.actorType })
      }

      return await signInAs(transacted, link, currentCustomerId)
    },
  )
}

async function signInAs(
  context: ActionContext,
  link: Selectable<MagicLinkTable>,
  currentCustomerId: CustomerId | null,
): Promise<MagicLinkSignIn> {
  const actorId = await claimActor(context, link, currentCustomerId)

  if (actorId === null) {
    return refused('unrecognized', { actor_type: link.actorType })
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
