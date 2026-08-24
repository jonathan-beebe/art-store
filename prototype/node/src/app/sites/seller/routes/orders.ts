import type { FastifyReply } from 'fastify'
import { z } from 'zod'
import { declineFulfillment } from '../../../actions/fulfillments/decline-fulfillment.ts'
import { markShipped } from '../../../actions/fulfillments/mark-shipped.ts'
import { openConversation } from '../../../actions/messaging/open-conversation.ts'
import type { FulfillmentId } from '../../../core/ids/entity-ids.ts'
import {
  canTransitionFulfillment,
  FULFILLMENT_STATUSES,
  type FulfillmentStatus,
} from '../../../core/orders/fulfillment-status.ts'
import { formatCents } from '../../../core/money.ts'
import { parseRefundReason, REFUND_REASON_MAX_LENGTH } from '../../../core/orders/refund.ts'
import { parseShipmentDetails } from '../../../core/orders/shipment-details.ts'
import { statusLabel } from '../../../core/status-label.ts'
import { TransitionError } from '../../../core/transition-error.ts'
import { idParams, submittedForm } from '../../../http/request-schema.ts'
import { requestActions } from '../../../http/request-actions.ts'
import type { ZodRoutes } from '../../../http/zod-type-provider.ts'
import { currentSellerId } from '../current-seller.ts'
import { formatDate, formatDateTime } from '../format.ts'
import { sellerNotFound } from '../not-found.ts'
import {
  fulfillmentsForSeller,
  itemTitlesByOrder,
  orderItemsForSeller,
  ownedFulfillment,
  refundForFulfillment,
  type FulfillmentWithOrder,
} from '../queries/fulfillments.ts'

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

/** Every refusal on this page says why and sends the seller back to it. */
function refuse(reply: FastifyReply, fulfillmentId: FulfillmentId, message: string): FastifyReply {
  reply.setFlash({ alert: message })

  return reply.redirect(`/seller/orders/${fulfillmentId}`)
}

export const ordersRoutes: ZodRoutes = (portal, _options, done) => {
  portal.get('/orders', async (request, reply) => {
    const { db } = request.server
    const sellerId = currentSellerId(request)
    const fulfillments = await fulfillmentsForSeller(db, sellerId)
    const itemTitles = await itemTitlesByOrder(
      db,
      fulfillments.map((fulfillment) => fulfillment.orderId),
      sellerId,
    )

    return reply.render('orders/index', {
      title: 'Orders',
      groups: groupByStatus(fulfillments),
      itemTitles,
      statusLabel,
      formatCents,
      formatDate,
    })
  })

  portal.get('/orders/:id', { schema: { params: idParams('ful') } }, async (request, reply) => {
    const { db } = request.server
    const sellerId = currentSellerId(request)
    const owned = await ownedFulfillment(db, sellerId, request.params.id)
    if (owned === null) return sellerNotFound(reply)

    const items = await orderItemsForSeller(db, owned.order.id, sellerId)

    return reply.render('orders/show', {
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
    })
  })

  portal.post(
    '/orders/:id/ship',
    { schema: { params: idParams('ful'), body: shipmentForm } },
    async (request, reply) => {
      const fulfillmentId = request.params.id
      const { db } = request.server
      const owned = await ownedFulfillment(db, currentSellerId(request), fulfillmentId)
      if (owned === null) return sellerNotFound(reply)

      const submitted = request.body
      const details = parseShipmentDetails({
        carrier: submitted.carrier,
        trackingNumber: submitted.tracking_number,
      })
      if (!details.ok) {
        return refuse(reply, fulfillmentId, Object.values(details.errors).join(' '))
      }

      try {
        await markShipped(requestActions(request), {
          fulfillmentId,
          carrier: details.value.carrier,
          trackingNumber: details.value.trackingNumber,
        })
      } catch (error) {
        if (error instanceof TransitionError) return refuse(reply, fulfillmentId, error.message)
        throw error
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
      if (!reason.ok) return refuse(reply, fulfillmentId, reason.error)

      try {
        await declineFulfillment(requestActions(request), {
          fulfillmentId,
          sellerId,
          reason: reason.value,
        })
      } catch (error) {
        if (error instanceof TransitionError) return refuse(reply, fulfillmentId, error.message)
        throw error
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
