import type { FastifyPluginCallback, FastifyReply, FastifyRequest } from 'fastify'
import { z } from 'zod'
import { blockCustomer } from '../../../actions/moderation/block-customer.ts'
import { liftCustomerBlock } from '../../../actions/moderation/lift-customer-block.ts'
import { liftListingRemoval } from '../../../actions/moderation/lift-listing-removal.ts'
import { removeListing } from '../../../actions/moderation/remove-listing.ts'
import type { ActionContext } from '../../../actions/action-context.ts'
import { resolveLocalRedirect } from '../../../core/auth/local-redirect.ts'
import { REMOVAL_KINDS } from '../../../core/moderation/listing-removal.ts'
import { TransitionError } from '../../../core/transition-error.ts'
import { BAD_REQUEST, renderErrorPage } from '../../../plugins/error-pages.ts'
import { formBody } from '../../../plugins/form-body.ts'
import { parseIdParam } from '../../../plugins/id-param.ts'
import { requestOrigin } from '../../auth/request-origin.ts'

/** Every moderation form carries where to go back to; a bare lift carries only that. */
const liftForm = z.object({ redirect_to: z.string().optional() })

const removalForm = liftForm.extend({
  kind: z.enum(REMOVAL_KINDS),
  reason: z.string().trim().min(1),
})

const blockForm = liftForm.extend({ reason: z.string().trim().min(1) })

/** What one moderation route needs beyond the shape every one of them shares. */
type ModerationCommand<Submitted extends { redirect_to?: string }> = {
  form: z.ZodType<Submitted>
  /** Where the page that offered the form lives, when the form named nowhere. */
  subjectPath(subjectId: number): string
  notice: string
  apply(
    context: ActionContext,
    input: { subjectId: number; adminId: number; submitted: Submitted },
  ): Promise<unknown>
}

/**
 * The four moderation writes differ only in their form, their action, and what
 * they say afterwards. The refusal is the action's to decide — a route that
 * asked whether a removal could be lifted would be holding the rule twice.
 */
function moderationRoute<Submitted extends { redirect_to?: string }>(
  command: ModerationCommand<Submitted>,
) {
  return async (request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply | void> => {
    const subjectId = parseIdParam(request.params)
    if (subjectId === null) return reply.callNotFound()

    const parsed = command.form.safeParse(formBody(request))
    if (!parsed.success) return renderErrorPage(reply, BAD_REQUEST)

    const submitted = parsed.data
    const destination = resolveLocalRedirect(submitted.redirect_to, {
      fallback: command.subjectPath(subjectId),
      origin: requestOrigin(request),
    })

    try {
      await command.apply(actionContext(request), {
        subjectId,
        adminId: currentAdminId(request),
        submitted,
      })
    } catch (error) {
      if (!(error instanceof TransitionError)) throw error

      reply.setFlash({ alert: error.message })

      return reply.redirect(destination)
    }

    reply.setFlash({ notice: command.notice })

    return reply.redirect(destination)
  }
}

function actionContext(request: FastifyRequest): ActionContext {
  return { db: request.server.db, clock: request.server.clock }
}

/** `requireAdmin` guards this whole plugin, so this only narrows the type. */
function currentAdminId(request: FastifyRequest): number {
  const { currentAdmin } = request

  if (currentAdmin === null) throw new Error('a moderation route needs a signed-in admin')

  return currentAdmin.id
}

const listingPath = (listingId: number): string => `/admin/listings/${listingId}`
const customerPath = (customerId: number): string => `/admin/customers/${customerId}`

export const moderationRoutes: FastifyPluginCallback = (admin, _options, done) => {
  admin.post(
    '/listings/:id/removals',
    moderationRoute({
      form: removalForm,
      subjectPath: listingPath,
      notice: 'Listing removed.',
      apply: (context, { subjectId, adminId, submitted }) =>
        removeListing(context, {
          listingId: subjectId,
          adminId,
          kind: submitted.kind,
          reason: submitted.reason,
        }),
    }),
  )

  admin.post(
    '/listings/:id/removals/lift',
    moderationRoute({
      form: liftForm,
      subjectPath: listingPath,
      notice: 'Removal lifted.',
      apply: (context, { subjectId }) => liftListingRemoval(context, { listingId: subjectId }),
    }),
  )

  admin.post(
    '/customers/:id/blocks',
    moderationRoute({
      form: blockForm,
      subjectPath: customerPath,
      notice: 'Customer blocked.',
      apply: (context, { subjectId, adminId, submitted }) =>
        blockCustomer(context, { customerId: subjectId, adminId, reason: submitted.reason }),
    }),
  )

  admin.post(
    '/customers/:id/blocks/lift',
    moderationRoute({
      form: liftForm,
      subjectPath: customerPath,
      notice: 'Block lifted.',
      apply: (context, { subjectId }) => liftCustomerBlock(context, { customerId: subjectId }),
    }),
  )

  done()
}
