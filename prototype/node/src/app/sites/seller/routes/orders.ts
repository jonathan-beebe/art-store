import type { FastifyPluginCallback, FastifyReply, FastifyRequest } from 'fastify'
import { z } from 'zod'
import { markShipped } from '../../../actions/fulfillments/mark-shipped.ts'
import { openConversation } from '../../../actions/messaging/open-conversation.ts'
import {
  canTransitionFulfillment,
  FULFILLMENT_STATUSES,
  type FulfillmentStatus,
} from '../../../core/orders/fulfillment-status.ts'
import { isShipmentComplete, parseShipmentDetails } from '../../../core/orders/shipment-details.ts'
import { formatCents } from '../../../core/money.ts'
import { statusLabel } from '../../../core/reports/status-label.ts'
import { TransitionError } from '../../../core/transition-error.ts'
import { currentSellerId } from '../current-seller.ts'
import { formatDate, formatDateTime } from '../format.ts'
import { sellerNotFound } from '../not-found.ts'
import { parseIdParam } from '../params.ts'
import {
  fulfillmentsForSeller,
  itemTitlesByOrder,
  orderItemsForSeller,
  ownedFulfillment,
  type FulfillmentWithOrder,
} from '../queries/fulfillments.ts'

const shipmentForm = z.object({ carrier: z.string().optional(), tracking_number: z.string().optional() })

function groupByStatus(
  fulfillments: readonly FulfillmentWithOrder[],
): readonly { status: FulfillmentStatus; fulfillments: readonly FulfillmentWithOrder[] }[] {
  return FULFILLMENT_STATUSES.map((status) => ({
    status,
    fulfillments: fulfillments.filter((fulfillment) => fulfillment.status === status),
  }))
}

async function index(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  const { db } = request.server
  const fulfillments = await fulfillmentsForSeller(db, currentSellerId(request))
  const itemTitles = await itemTitlesByOrder(
    db,
    fulfillments.map((fulfillment) => fulfillment.orderId),
    currentSellerId(request),
  )

  return reply.render('orders/index', {
    title: 'Orders',
    groups: groupByStatus(fulfillments),
    itemTitles,
    statusLabel,
    formatCents,
    formatDate,
  })
}

async function show(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  const id = parseIdParam(request.params)
  if (id === null) return sellerNotFound(reply)

  const { db } = request.server
  const owned = await ownedFulfillment(db, currentSellerId(request), id)
  if (owned === null) return sellerNotFound(reply)

  const items = await orderItemsForSeller(db, owned.order.id, currentSellerId(request))

  return reply.render('orders/show', {
    title: `Order #${owned.order.id}`,
    fulfillment: owned.fulfillment,
    order: owned.order,
    items,
    canShip: canTransitionFulfillment(owned.fulfillment.status, 'shipped'),
    statusLabel,
    formatCents,
    formatDateTime,
  })
}

async function ship(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  const id = parseIdParam(request.params)
  if (id === null) return sellerNotFound(reply)

  const { db } = request.server
  const owned = await ownedFulfillment(db, currentSellerId(request), id)
  if (owned === null) return sellerNotFound(reply)

  const submitted = shipmentForm.parse(request.body)
  const details = parseShipmentDetails({ carrier: submitted.carrier, trackingNumber: submitted.tracking_number })
  if (!isShipmentComplete(details)) return refuseShipment(reply, id, 'A shipment needs a carrier and a tracking number.')

  try {
    await markShipped(
      { db, clock: request.server.clock },
      { fulfillmentId: id, carrier: details.carrier, trackingNumber: details.trackingNumber },
    )
  } catch (error) {
    if (error instanceof TransitionError) return refuseShipment(reply, id, error.message)
    throw error
  }

  reply.setFlash({ notice: 'Marked shipped.' })
  return reply.redirect(`/seller/orders/${id}`)
}

function refuseShipment(reply: FastifyReply, fulfillmentId: number, message: string): FastifyReply {
  reply.setFlash({ alert: message })
  return reply.redirect(`/seller/orders/${fulfillmentId}`)
}

async function openMessageThread(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  const id = parseIdParam(request.params)
  if (id === null) return sellerNotFound(reply)

  const { db, clock } = request.server
  const owned = await ownedFulfillment(db, currentSellerId(request), id)
  if (owned === null) return sellerNotFound(reply)

  const conversation = await openConversation(
    { db, clock },
    {
      kind: 'fulfillment',
      sellerId: currentSellerId(request),
      customerId: owned.order.customerId,
      fulfillmentId: owned.fulfillment.id,
    },
  )

  return reply.redirect(`/seller/messages/${conversation.id}`)
}

export const ordersRoutes: FastifyPluginCallback = (portal, _options, done) => {
  portal.get('/orders', index)
  portal.get('/orders/:id', show)
  portal.post('/orders/:id/ship', ship)
  portal.post('/orders/:id/messages', openMessageThread)

  done()
}
