import type { FastifyReply, FastifyRequest } from 'fastify'
import { z } from 'zod'
import { sendMagicLink } from '../../actions/auth/send-magic-link.ts'
import { ACTOR_SITES, type ActorType } from '../../core/auth/actor-type.ts'
import { isEmailAddress, normalizeEmail } from '../../core/auth/email-address.ts'
import { keepLocalRedirect } from '../../core/auth/local-redirect.ts'
import type { AppDatabase } from '../../db/database.ts'
import { submittedForm } from '../../http/request-schema.ts'
import { requestActions } from '../../http/request-actions.ts'
import type { ZodRoutes } from '../../http/zod-type-provider.ts'
import { ACTOR_GUARDS, rememberCustomerIdentity, signedInActorId } from '../../plugins/identity.ts'
import { magicLinkRequestGuard } from '../../plugins/rate-limit.ts'
import { magicLinkUrl, requestOrigin } from './request-origin.ts'

const NO_ADDRESS = 'Enter an email address to sign in.'

const signInQuery = z.object({ redirect_to: z.string().optional() })

const signInForm = submittedForm({
  email: z.string().optional(),
  redirect_to: z.string().optional(),
})

type SignInForm = z.output<typeof signInForm>

export type SignInRoutesOptions = {
  actorType: ActorType
  /** Decides whether an address may be sent a link at all. */
  admits?: (db: AppDatabase, email: string) => Promise<boolean>
  /** Shown when `admits` refuses the address. */
  refusal?: string
  /** What this site's account page shows beyond the identity behind it. */
  accountView?: (request: FastifyRequest) => Promise<Record<string, unknown>>
}

/**
 * The sign-in form, the link request, sign-out, and the account page for one
 * side of the marketplace. Each site registers this inside itself, so the pages
 * render in that site's layout and the paths sit under that site's prefix.
 *
 * A customer's identity is read here but never created: the storefront's own
 * hook owns that, so asking for a link leaves no anonymous row behind.
 */
export function signInRoutes({
  actorType,
  admits,
  refusal,
  accountView,
}: SignInRoutesOptions): ZodRoutes {
  const site = ACTOR_SITES[actorType]

  const refuseLink = async (
    reply: FastifyReply,
    alert: string,
    redirectTo: string | null,
  ): Promise<FastifyReply> => {
    reply.setFlash({ alert })

    return await reply.redirect(loginPath(site.loginPath, redirectTo))
  }

  const signInPages: ZodRoutes = (routes, _options, done) => {
    if (actorType === 'customer') routes.addHook('preHandler', rememberCustomerIdentity)

    routes.get('/login', { schema: { querystring: signInQuery } }, async (request, reply) => {
      if (signedInActorId(request, actorType) !== null) return await reply.redirect(site.homePath)

      return reply.render('login', {
        title: 'Sign in',
        loginPath: site.loginPath,
        redirectTo: keepLocalRedirect(request.query.redirect_to, requestOrigin(request)),
      })
    })

    routes.post(
      '/login',
      {
        schema: { body: signInForm },
        preHandler: magicLinkRequestGuard<SignInForm>((request) => request.body.email ?? ''),
      },
      async (request, reply) => {
        const submitted = request.body
        const redirectTo = keepLocalRedirect(submitted.redirect_to, requestOrigin(request))

        if (!isEmailAddress(submitted.email)) return await refuseLink(reply, NO_ADDRESS, redirectTo)

        const email = normalizeEmail(submitted.email)
        if (admits !== undefined && !(await admits(routes.db, email))) {
          return await refuseLink(reply, refusal ?? NO_ADDRESS, redirectTo)
        }

        // The action logs the request; neither the address nor the token reaches
        // the log. `delivered` carries the debug magic link, and the flash it
        // becomes is the only place that link is allowed.
        const delivered = await sendMagicLink(
          {
            ...requestActions(request),
            delivery: routes.magicLinkDelivery,
            magicLinkUrl: (token) => magicLinkUrl(request, token),
          },
          { email, actorType, redirectTo },
        )

        reply.setFlash({ ...delivered, notice: `Sign-in link sent to ${email}.` })

        return await reply.redirect(loginPath(site.loginPath, redirectTo))
      },
    )

    routes.post('/logout', async (_request, reply) => {
      reply.signOut(actorType)
      reply.setFlash({ notice: 'Signed out.' })

      return await reply.redirect(site.signedOutPath)
    })

    routes.get('/account', { preHandler: ACTOR_GUARDS[actorType] }, async (request, reply) =>
      reply.render('account', { title: 'Account', ...(await accountView?.(request)) }),
    )

    done()
  }

  return signInPages
}

function loginPath(path: string, redirectTo: string | null): string {
  return redirectTo === null ? path : `${path}?redirect_to=${encodeURIComponent(redirectTo)}`
}
