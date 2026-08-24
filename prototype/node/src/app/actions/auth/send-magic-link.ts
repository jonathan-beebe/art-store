import { randomBytes } from 'node:crypto'
import type { ActorType } from '../../core/auth/actor-type.ts'
import { normalizeEmail } from '../../core/auth/email-address.ts'
import { magicLinkExpiresAt } from '../../core/auth/magic-link-status.ts'
import { digestMagicLinkToken } from '../../core/auth/magic-link-token.ts'
import { toTimestamp } from '../../db/timestamp.ts'
import type { MagicLinkDelivery } from '../../delivery/magic-link-delivery.ts'
import { newId } from '../../ids.ts'
import type { Flash } from '../../plugins/flash.ts'
import type { ActionContext } from '../action-context.ts'
import { actionStory } from '../action-story.ts'

const TOKEN_BYTES = 32

export type SendMagicLinkDependencies = ActionContext & {
  delivery: MagicLinkDelivery
  /** Turns a token into the URL to click; the host it needs belongs to the request. */
  magicLinkUrl: (token: string) => string
}

export type SendMagicLinkInput = {
  email: string
  actorType: ActorType
  redirectTo?: string | null
}

/**
 * Issues one link and hands it to the delivery, returning whatever the delivery
 * wants the next page to show. The token itself is never stored, so this is the
 * only moment it exists. The link and whatever the delivery queues for it are
 * written in one transaction, so neither exists without the other.
 *
 * Neither the address nor the token reaches the log: the link's own id is what
 * joins these lines to the `magic_link.consume` lines that follow.
 */
export async function sendMagicLink(
  dependencies: SendMagicLinkDependencies,
  { email, actorType, redirectTo = null }: SendMagicLinkInput,
): Promise<Flash> {
  const { db, clock, log, delivery, magicLinkUrl } = dependencies
  const token = randomBytes(TOKEN_BYTES).toString('hex')
  const address = normalizeEmail(email)
  const issuedAt = clock.now()
  const linkId = newId('mlk', issuedAt)

  return actionStory<Flash>(
    { db, clock, log },
    {
      event: 'magic_link.request',
      will: {
        msg: `issuing a sign-in link for the ${actorType} site`,
        data: { magic_link_id: linkId, actor_type: actorType, redirect_to: redirectTo },
      },
      ended: () => ({
        phase: 'did',
        msg: 'issued the sign-in link',
        data: { magic_link_id: linkId, actor_type: actorType },
      }),
    },
    async (transacted) => {
      await transacted.db
        .insertInto('magicLinks')
        .values({
          id: linkId,
          tokenDigest: digestMagicLinkToken(token),
          email: address,
          actorType,
          redirectTo,
          expiresAt: toTimestamp(magicLinkExpiresAt(issuedAt)),
          consumedAt: null,
          createdAt: toTimestamp(issuedAt),
        })
        .execute()

      return delivery.deliver(transacted, { email: address, url: magicLinkUrl(token), actorType })
    },
  )
}
