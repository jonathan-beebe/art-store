import type { FastifyReply } from 'fastify'
import { z } from 'zod'
import { resolveCustomerFromCookie } from '../../actions/customers/resolve-customer-from-cookie.ts'
import {
  signInWithMagicLink,
  type MagicLinkRefusal,
} from '../../actions/auth/sign-in-with-magic-link.ts'
import { ACTOR_SITES, type ActorType } from '../../core/auth/actor-type.ts'
import { resolveLocalRedirect } from '../../core/auth/local-redirect.ts'
import { requestActions } from '../../http/request-actions.ts'
import type { ZodRoutes } from '../../http/zod-type-provider.ts'
import { identityId } from '../../plugins/identity.ts'
import { clientIp, rateLimitGuard } from '../../plugins/rate-limit.ts'
import { requestOrigin } from './request-origin.ts'

const REFUSALS = {
  expired: 'That sign-in link has expired. Ask for a new one.',
  consumed: 'That sign-in link has already been used. Ask for a new one.',
  unrecognized: 'That address cannot sign in here. Ask for a new link.',
} as const satisfies Record<MagicLinkRefusal, string>

const UNKNOWN_LINK = 'That sign-in link is not valid. Ask for a new one.'

const linkParams = z.object({ token: z.string().min(1) })

/**
 * One route for all three sides of the marketplace: the link itself names the
 * side it signs in, so nothing about it belongs to a particular site.
 */
export const authSite: ZodRoutes = (auth, _options, done) => {
  auth.get(
    '/auth/magic/:token',
    {
      schema: { params: linkParams },
      preHandler: rateLimitGuard({ name: 'magic_link_consume', key: clientIp }),
    },
    async (request, reply) => {
      const actions = requestActions(request)
      const remembered = await resolveCustomerFromCookie(actions, identityId(request, 'customer'))

      const signIn = await signInWithMagicLink(actions, {
        token: request.params.token,
        currentCustomerId: remembered?.id ?? null,
      })

      if (signIn.outcome === 'unknown') return refuse(reply, 'customer', UNKNOWN_LINK)
      if (signIn.outcome === 'refused') {
        return refuse(reply, signIn.actorType, REFUSALS[signIn.refusal])
      }

      reply.signIn(signIn.actorType, signIn.actorId)

      return await reply.redirect(
        resolveLocalRedirect(signIn.redirectTo, {
          actorType: signIn.actorType,
          fallback: ACTOR_SITES[signIn.actorType].homePath,
          origin: requestOrigin(request),
        }),
      )
    },
  )

  done()
}

function refuse(reply: FastifyReply, actorType: ActorType, alert: string): FastifyReply {
  reply.setFlash({ alert })

  return reply.redirect(ACTOR_SITES[actorType].loginPath)
}
