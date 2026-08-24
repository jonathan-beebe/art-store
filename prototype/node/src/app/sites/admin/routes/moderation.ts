import type { FastifyReply, FastifyRequest } from 'fastify'
import { z } from 'zod'
import type { ActionContext } from '../../../actions/action-context.ts'
import { blockCustomer } from '../../../actions/moderation/block-customer.ts'
import { liftCustomerBlock } from '../../../actions/moderation/lift-customer-block.ts'
import { liftListingRemoval } from '../../../actions/moderation/lift-listing-removal.ts'
import { removeListing } from '../../../actions/moderation/remove-listing.ts'
import { resolveLocalRedirect } from '../../../core/auth/local-redirect.ts'
import type { AdminId, CustomerId, IdPrefix, ListingId } from '../../../core/ids/entity-ids.ts'
import type { PrefixedId } from '../../../core/ids/prefixed-id.ts'
import { REMOVAL_KINDS } from '../../../core/moderation/listing-removal.ts'
import { TransitionError } from '../../../core/transition-error.ts'
import { idParams, submittedForm } from '../../../http/request-schema.ts'
import type { ZodRoutes } from '../../../http/zod-type-provider.ts'
import { requestOrigin } from '../../auth/request-origin.ts'

/** Every moderation form carries where to go back to; a bare lift carries only that. */
const RETURN_FIELD = { redirect_to: z.string().optional() }
const REASON_FIELD = { reason: z.string().trim().min(1) }

const liftForm = submittedForm(RETURN_FIELD)
const removalForm = submittedForm({ ...RETURN_FIELD, ...REASON_FIELD, kind: z.enum(REMOVAL_KINDS) })
const blockForm = submittedForm({ ...RETURN_FIELD, ...REASON_FIELD })

/** What every moderation form carries, whatever else it holds. */
type ModerationForm = { redirect_to?: string }

/** What one moderation route reads: the subject its url names and its own form. */
type ModerationRequest<Submitted, Prefix extends IdPrefix> = FastifyRequest & {
  params: { id: PrefixedId<Prefix> }
  body: Submitted
}

/** What one moderation route needs beyond the shape every one of them shares. */
type ModerationCommand<Submitted extends ModerationForm, Prefix extends IdPrefix> = {
  /** The table the `:id` segment names; another table's id answers 404. */
  subjectPrefix: Prefix
  /** The form this route accepts, declared on the route and read as its body. */
  form: z.ZodType<Submitted>
  /** Where the page that offered the form lives, when the form named nowhere. */
  subjectPath(subjectId: PrefixedId<Prefix>): string
  notice: string
  apply(
    context: ActionContext,
    input: { subjectId: PrefixedId<Prefix>; adminId: AdminId; submitted: Submitted },
  ): Promise<unknown>
  /** The business event this write logs once `apply` has succeeded. */
  logEvent(
    subjectId: PrefixedId<Prefix>,
    adminId: AdminId,
    submitted: Submitted,
  ): Record<string, unknown>
}

/**
 * The four moderation writes differ only in their form, their action, and what
 * they say afterwards. The refusal is the action's to decide — a route that
 * asked whether a removal could be lifted would be holding the rule twice.
 */
function moderationRoute<Submitted extends ModerationForm, Prefix extends IdPrefix>(
  command: ModerationCommand<Submitted, Prefix>,
) {
  const handler = async (
    request: ModerationRequest<Submitted, Prefix>,
    reply: FastifyReply,
  ): Promise<FastifyReply> => {
    const subjectId = request.params.id
    const submitted = request.body
    const destination = resolveLocalRedirect(submitted.redirect_to, {
      fallback: command.subjectPath(subjectId),
      origin: requestOrigin(request),
    })

    const adminId = currentAdminId(request)

    try {
      await command.apply(actionContext(request), { subjectId, adminId, submitted })
    } catch (error) {
      if (!(error instanceof TransitionError)) throw error

      reply.setFlash({ alert: error.message })

      return reply.redirect(destination)
    }

    request.log.info(command.logEvent(subjectId, adminId, submitted), command.notice)
    reply.setFlash({ notice: command.notice })

    return reply.redirect(destination)
  }

  return { schema: { params: idParams(command.subjectPrefix), body: command.form }, handler }
}

function actionContext(request: FastifyRequest): ActionContext {
  return { db: request.server.db, clock: request.server.clock }
}

/** `requireAdmin` guards this whole plugin, so this only narrows the type. */
function currentAdminId(request: FastifyRequest): AdminId {
  const { currentAdmin } = request

  if (currentAdmin === null) throw new Error('a moderation route needs a signed-in admin')

  return currentAdmin.id
}

const listingPath = (listingId: ListingId): string => `/admin/listings/${listingId}`
const customerPath = (customerId: CustomerId): string => `/admin/customers/${customerId}`

export const moderationRoutes: ZodRoutes = (admin, _options, done) => {
  admin.post(
    '/listings/:id/removals',
    moderationRoute({
      subjectPrefix: 'lst',
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
      logEvent: (listingId, adminId, submitted) => ({
        event: 'moderation.listing_removed',
        listingId,
        adminId,
        kind: submitted.kind,
        reason: submitted.reason,
      }),
    }),
  )

  admin.post(
    '/listings/:id/removals/lift',
    moderationRoute({
      subjectPrefix: 'lst',
      form: liftForm,
      subjectPath: listingPath,
      notice: 'Removal lifted.',
      apply: (context, { subjectId }) => liftListingRemoval(context, { listingId: subjectId }),
      logEvent: (listingId, adminId) => ({
        event: 'moderation.listing_removal_lifted',
        listingId,
        adminId,
      }),
    }),
  )

  admin.post(
    '/customers/:id/blocks',
    moderationRoute({
      subjectPrefix: 'cus',
      form: blockForm,
      subjectPath: customerPath,
      notice: 'Customer blocked.',
      apply: (context, { subjectId, adminId, submitted }) =>
        blockCustomer(context, { customerId: subjectId, adminId, reason: submitted.reason }),
      logEvent: (customerId, adminId, submitted) => ({
        event: 'moderation.customer_blocked',
        customerId,
        adminId,
        reason: submitted.reason,
      }),
    }),
  )

  admin.post(
    '/customers/:id/blocks/lift',
    moderationRoute({
      subjectPrefix: 'cus',
      form: liftForm,
      subjectPath: customerPath,
      notice: 'Block lifted.',
      apply: (context, { subjectId }) => liftCustomerBlock(context, { customerId: subjectId }),
      logEvent: (customerId, adminId) => ({
        event: 'moderation.customer_block_lifted',
        customerId,
        adminId,
      }),
    }),
  )

  done()
}
