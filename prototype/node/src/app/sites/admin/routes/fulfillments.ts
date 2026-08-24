import type { FastifyRequest } from 'fastify'
import { z } from 'zod'
import { issueRefund } from '../../../actions/refunds/issue-refund.ts'
import { resolveLocalRedirect } from '../../../core/auth/local-redirect.ts'
import type { AdminId } from '../../../core/ids/entity-ids.ts'
import { FULFILLMENT_STATUSES } from '../../../core/orders/fulfillment-status.ts'
import { parseRefundReason, REFUND_REASON_MAX_LENGTH } from '../../../core/orders/refund.ts'
import { TransitionError } from '../../../core/transition-error.ts'
import { idParams, idValue, optionalFilter, submittedForm } from '../../../http/request-schema.ts'
import { requestActions } from '../../../http/request-actions.ts'
import type { ZodRoutes } from '../../../http/zod-type-provider.ts'
import { adminPage } from '../page.ts'
import { fulfillmentDetail } from '../queries/fulfillment-detail.ts'
import { fulfillmentRows } from '../queries/fulfillment-rows.ts'
import { requestOrigin } from '../../auth/request-origin.ts'

/** The refund form carries where to go back to: the order page offers one too. */
const refundForm = submittedForm({ reason: z.string().optional(), redirect_to: z.string().optional() })

/** `requireAdmin` guards this whole plugin, so this only narrows the type. */
function currentAdminId(request: FastifyRequest): AdminId {
  const { currentAdmin } = request

  if (currentAdmin === null) throw new Error('a refund route needs a signed-in admin')

  return currentAdmin.id
}

const fulfillmentsQuery = z.object({
  status: optionalFilter(z.enum(FULFILLMENT_STATUSES)),
  seller: optionalFilter(idValue('sel')),
})

export const fulfillmentRoutes: ZodRoutes = (admin, _options, done) => {
  admin.get(
    '/fulfillments',
    { schema: { querystring: fulfillmentsQuery } },
    async (request, reply) => {
      const { status, seller } = request.query
      const fulfillments = await fulfillmentRows({ db: admin.db }, { status, sellerId: seller })

      return reply.render(
        'fulfillments',
        adminPage('Fulfillments', {
          fulfillments,
          statuses: FULFILLMENT_STATUSES,
          filters: { status: status ?? '', seller: seller ?? '' },
        }),
      )
    },
  )

  admin.get('/fulfillments/:id', { schema: { params: idParams('ful') } }, async (request, reply) => {
    const detail = await fulfillmentDetail({ db: admin.db }, request.params.id)
    if (detail === null) return reply.callNotFound()

    return reply.render(
      'fulfillment',
      adminPage(`Fulfillment ${detail.fulfillment.id}`, {
        ...detail,
        reasonMaxLength: REFUND_REASON_MAX_LENGTH,
      }),
    )
  })

  admin.post(
    '/fulfillments/:id/refund',
    { schema: { params: idParams('ful'), body: refundForm } },
    async (request, reply) => {
      const fulfillmentId = request.params.id
      const destination = resolveLocalRedirect(request.body.redirect_to, {
        actorType: 'admin',
        fallback: `/admin/fulfillments/${fulfillmentId}`,
        origin: requestOrigin(request),
      })
      const found = await admin.db
        .selectFrom('fulfillments')
        .select('id')
        .where('id', '=', fulfillmentId)
        .executeTakeFirst()
      if (found === undefined) return reply.callNotFound()

      const reason = parseRefundReason(request.body.reason)
      if (!reason.ok) {
        reply.setFlash({ alert: reason.error })

        return reply.redirect(destination)
      }

      try {
        await issueRefund(requestActions(request), {
          fulfillmentId,
          reason: reason.value,
          issuedBy: { type: 'admin', id: currentAdminId(request) },
        })
      } catch (error) {
        if (!(error instanceof TransitionError)) throw error

        reply.setFlash({ alert: error.message })

        return reply.redirect(destination)
      }

      reply.setFlash({ notice: 'Refund issued.' })

      return reply.redirect(destination)
    },
  )

  done()
}
