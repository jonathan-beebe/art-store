import type { FastifyReply, FastifyRequest } from 'fastify'
import { z } from 'zod'
import { declineFulfillment } from '../../../actions/fulfillments/decline-fulfillment.ts'
import { markShipped } from '../../../actions/fulfillments/mark-shipped.ts'
import { openConversation } from '../../../actions/messaging/open-conversation.ts'
import {
  canTransitionFulfillment,
  FULFILLMENT_STATUSES,
  type FulfillmentStatus,
} from '../../../core/orders/fulfillment-status.ts'
import { formatCents } from '../../../core/money.ts'
import { listPage } from '../../../core/paging/list-page.ts'
import { parseRefundReason, REFUND_REASON_MAX_LENGTH, type RefundReasonErrors } from '../../../core/orders/refund.ts'
import { parseShipmentDetails, type ShipmentDetailsErrors } from '../../../core/orders/shipment-details.ts'
import { statusLabel } from '../../../core/status-label.ts'
import type { Fulfillment, Order } from '../../../db/commerce-schema.ts'
import { idParams, submittedForm } from '../../../http/request-schema.ts'
import { requestActions } from '../../../http/request-actions.ts'
import type { ZodRoutes } from '../../../http/zod-type-provider.ts'
import { currentSellerId } from '../current-seller.ts'
import { formatDate, formatDateTime } from '../format.ts'
import { sellerNotFound } from '../not-found.ts'
import {
  fulfillmentCountsByStatus,
  fulfillmentsForSeller,
  itemTitlesByOrder,
  orderItemsForSeller,
  ownedFulfillment,
  refundForFulfillment,
  type FulfillmentWithOrder,
} from '../queries/fulfillments.ts'

// A page of orders deep enough that most sellers never see a second one.
const ORDERS_PER_PAGE = 25

const indexQuery = z.object({ page: z.string().optional() })

const shipmentForm = submittedForm({
  carrier: z.string().optional(),
  tracking_number: z.string().optional(),
})

const declineForm = submittedForm({ reason: z.string().optional() })

function groupByStatus(
  fulfillments: readonly FulfillmentWithOrder[],
): readonly { status: FulfillmentStatus; fulfillments: readonly FulfillmentWithOrder[] }[] {
  return FULFILLMENT_STATUSES.map((status) => ({
    status,
    fulfillments: fulfillments.filter((fulfillment) => fulfillment.status === status),
  }))
}

/** What the ship form on the order page shows back: the values as typed, an
 * error beside a bad field, or a field-less refusal for the shared slot. */
type ShipFormState = { carrier?: string; trackingNumber?: string; errors?: ShipmentDetailsErrors; formError?: string }

/** What the decline form on the order page shows back, the same way. */
type DeclineFormState = { reason?: string; errors?: RefundReasonErrors; formError?: string }

type OwnedOrder = { fulfillment: Fulfillment; order: Order }

/** The ship form's state, defaulted for a page that carries no refused
 * submission of its own. */
function shipFormStateOrBlank(
  state: ShipFormState | undefined,
): { carrier: string; trackingNumber: string; errors: ShipmentDetailsErrors; formError: string | null } {
  if (state === undefined) return { carrier: '', trackingNumber: '', errors: {}, formError: null }

  return {
    carrier: state.carrier ?? '',
    trackingNumber: state.trackingNumber ?? '',
    errors: state.errors ?? {},
    formError: state.formError ?? null,
  }
}

/** The decline form's state, the same way. */
function declineFormStateOrBlank(
  state: DeclineFormState | undefined,
): { reason: string; errors: RefundReasonErrors; formError: string | null } {
  if (state === undefined) return { reason: '', errors: {}, formError: null }

  return { reason: state.reason ?? '', errors: state.errors ?? {}, formError: state.formError ?? null }
}

/**
 * The order page, blank or carrying one of its two forms' refused submission.
 * Both forms share the page, so a refusal on either re-renders the whole
 * thing with the other form still at its saved values.
 */
async function renderOrderShow(
  request: FastifyRequest,
  reply: FastifyReply,
  owned: OwnedOrder,
  opts: { ship?: ShipFormState; decline?: DeclineFormState } = {},
  status?: number,
): Promise<FastifyReply> {
  const { db } = request.server
  const items = await orderItemsForSeller(db, owned.order.id, currentSellerId(request))
  const rendered = status === undefined ? reply : reply.code(status)
  const ship = shipFormStateOrBlank(opts.ship)
  const decline = declineFormStateOrBlank(opts.decline)

  return rendered.render('orders/show', {
    title: `Order ${owned.order.id}`,
    fulfillment: owned.fulfillment,
    order: owned.order,
    items,
    canShip: canTransitionFulfillment(owned.fulfillment.status, 'shipped'),
    canDecline: canTransitionFulfillment(owned.fulfillment.status, 'declined'),
    reasonMaxLength: REFUND_REASON_MAX_LENGTH,
    refund: await refundForFulfillment(db, owned.fulfillment.id),
    statusLabel,
    formatCents,
    formatDateTime,
    shipCarrier: ship.carrier,
    shipTrackingNumber: ship.trackingNumber,
    shipErrors: ship.errors,
    shipFormError: ship.formError,
    declineReason: decline.reason,
    declineErrors: decline.errors,
    declineFormError: decline.formError,
  })
}

export const ordersRoutes: ZodRoutes = (portal, _options, done) => {
  portal.get('/orders', { schema: { querystring: indexQuery } }, async (request, reply) => {
    const { db } = request.server
    const sellerId = currentSellerId(request)
    const counts = await fulfillmentCountsByStatus(db, sellerId)
    const totalCount = [...counts.values()].reduce((sum, count) => sum + count, 0)
    const page = listPage({ requested: request.query.page, size: ORDERS_PER_PAGE, totalCount })
    const fulfillments = await fulfillmentsForSeller(db, sellerId, page)
    const itemTitles = await itemTitlesByOrder(
      db,
      fulfillments.map((fulfillment) => fulfillment.orderId),
      sellerId,
    )

    return reply.render('orders/index', {
      title: 'Orders',
      groups: groupByStatus(fulfillments),
      counts,
      page,
      itemTitles,
      statusLabel,
      formatCents,
      formatDate,
    })
  })

  portal.get('/orders/:id', { schema: { params: idParams('ful') } }, async (request, reply) => {
    const owned = await ownedFulfillment(request.server.db, currentSellerId(request), request.params.id)
    if (owned === null) return sellerNotFound(reply)

    return renderOrderShow(request, reply, owned)
  })

  portal.post(
    '/orders/:id/ship',
    { schema: { params: idParams('ful'), body: shipmentForm } },
    async (request, reply) => {
      const fulfillmentId = request.params.id
      const owned = await ownedFulfillment(request.server.db, currentSellerId(request), fulfillmentId)
      if (owned === null) return sellerNotFound(reply)

      const submitted = request.body
      const details = parseShipmentDetails({
        carrier: submitted.carrier,
        trackingNumber: submitted.tracking_number,
      })
      if (!details.ok) {
        return renderOrderShow(
          request,
          reply,
          owned,
          {
            ship: { carrier: submitted.carrier ?? '', trackingNumber: submitted.tracking_number ?? '', errors: details.errors },
          },
          422,
        )
      }

      const result = await markShipped(requestActions(request), {
        fulfillmentId,
        carrier: details.value.carrier,
        trackingNumber: details.value.trackingNumber,
      })
      if (result.outcome === 'refused') {
        return renderOrderShow(
          request,
          reply,
          owned,
          {
            ship: {
              carrier: details.value.carrier,
              trackingNumber: details.value.trackingNumber,
              formError: `A fulfillment cannot move from ${owned.fulfillment.status} to shipped.`,
            },
          },
          422,
        )
      }

      reply.setFlash({ notice: 'Marked shipped.' })

      return reply.redirect(`/seller/orders/${fulfillmentId}`)
    },
  )

  portal.post(
    '/orders/:id/decline',
    { schema: { params: idParams('ful'), body: declineForm } },
    async (request, reply) => {
      const fulfillmentId = request.params.id
      const sellerId = currentSellerId(request)
      const owned = await ownedFulfillment(request.server.db, sellerId, fulfillmentId)
      if (owned === null) return sellerNotFound(reply)

      const reason = parseRefundReason(request.body.reason)
      if (!reason.ok) {
        return renderOrderShow(request, reply, owned, { decline: { reason: request.body.reason ?? '', errors: reason.errors } }, 422)
      }

      const result = await declineFulfillment(requestActions(request), {
        fulfillmentId,
        sellerId,
        reason: reason.value,
      })
      if (result.outcome === 'refused') {
        const message =
          result.reason === 'order_unpaid'
            ? 'An order that has not been paid cannot be refunded.'
            : `A fulfillment cannot move from ${owned.fulfillment.status} to declined.`

        return renderOrderShow(request, reply, owned, { decline: { reason: reason.value, formError: message } }, 422)
      }

      reply.setFlash({ notice: 'Declined. The customer has been refunded.' })

      return reply.redirect(`/seller/orders/${fulfillmentId}`)
    },
  )

  portal.post('/orders/:id/messages', { schema: { params: idParams('ful') } }, async (request, reply) => {
    const { db } = request.server
    const sellerId = currentSellerId(request)
    const owned = await ownedFulfillment(db, sellerId, request.params.id)
    if (owned === null) return sellerNotFound(reply)

    const conversation = await openConversation(requestActions(request), {
      kind: 'fulfillment',
      sellerId,
      customerId: owned.order.customerId,
      fulfillmentId: owned.fulfillment.id,
    })

    return reply.redirect(`/seller/messages/${conversation.id}`)
  })

  done()
}
