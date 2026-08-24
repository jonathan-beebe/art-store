import type { FastifyReply } from 'fastify'
import { z } from 'zod'
import { markShipped } from '../../../actions/fulfillments/mark-shipped.ts'
import { openConversation } from '../../../actions/messaging/open-conversation.ts'
import type { FulfillmentId } from '../../../core/ids/entity-ids.ts'
import {
  canTransitionFulfillment,
  FULFILLMENT_STATUSES,
  type FulfillmentStatus,
} from '../../../core/orders/fulfillment-status.ts'
import { formatCents } from '../../../core/money.ts'
import { parseShipmentDetails } from '../../../core/orders/shipment-details.ts'
import { statusLabel } from '../../../core/status-label.ts'
import { TransitionError } from '../../../core/transition-error.ts'
import { idParams, submittedForm } from '../../../http/request-schema.ts'
import type { ZodRoutes } from '../../../http/zod-type-provider.ts'
import { currentSellerId } from '../current-seller.ts'
import { formatDate, formatDateTime } from '../format.ts'
import { sellerNotFound } from '../not-found.ts'
import {
  fulfillmentsForSeller,
  itemTitlesByOrder,
  orderItemsForSeller,
  ownedFulfillment,
  type FulfillmentWithOrder,
} from '../queries/fulfillments.ts'

const shipmentForm = submittedForm({
  carrier: z.string().optional(),
  tracking_number: z.string().optional(),
})

function groupByStatus(
  fulfillments: readonly FulfillmentWithOrder[],
): readonly { status: FulfillmentStatus; fulfillments: readonly FulfillmentWithOrder[] }[] {
  return FULFILLMENT_STATUSES.map((status) => ({
    status,
    fulfillments: fulfillments.filter((fulfillment) => fulfillment.status === status),
  }))
}

function refuseShipment(reply: FastifyReply, fulfillmentId: FulfillmentId, message: string): FastifyReply {
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
      const { db, clock } = request.server
      const owned = await ownedFulfillment(db, currentSellerId(request), fulfillmentId)
      if (owned === null) return sellerNotFound(reply)

      const submitted = request.body
      const details = parseShipmentDetails({
        carrier: submitted.carrier,
        trackingNumber: submitted.tracking_number,
      })
      if (!details.ok) {
        return refuseShipment(reply, fulfillmentId, Object.values(details.errors).join(' '))
      }

      try {
        const shipped = await markShipped(
          { db, clock },
          {
            fulfillmentId,
            carrier: details.value.carrier,
            trackingNumber: details.value.trackingNumber,
          },
        )

        request.log.info(
          {
            event: 'fulfillment.shipped',
            fulfillmentId: shipped.id,
            orderId: shipped.orderId,
            sellerId: shipped.sellerId,
          },
          'fulfillment shipped',
        )
      } catch (error) {
        if (error instanceof TransitionError) return refuseShipment(reply, fulfillmentId, error.message)
        throw error
      }

      reply.setFlash({ notice: 'Marked shipped.' })

      return reply.redirect(`/seller/orders/${fulfillmentId}`)
    },
  )

  portal.post('/orders/:id/messages', { schema: { params: idParams('ful') } }, async (request, reply) => {
    const { db, clock } = request.server
    const sellerId = currentSellerId(request)
    const owned = await ownedFulfillment(db, sellerId, request.params.id)
    if (owned === null) return sellerNotFound(reply)

    const conversation = await openConversation(
      { db, clock },
      {
        kind: 'fulfillment',
        sellerId,
        customerId: owned.order.customerId,
        fulfillmentId: owned.fulfillment.id,
      },
    )

    return reply.redirect(`/seller/messages/${conversation.id}`)
  })

  done()
}
