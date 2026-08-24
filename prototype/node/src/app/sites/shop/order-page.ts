import type { FastifyInstance, FastifyReply, FastifyRequest } from 'fastify'
import type { FulfillmentId, OrderId } from '../../core/ids/entity-ids.ts'
import { isPayable, isUnpaid } from '../../core/orders/order-payment.ts'
import { isCancellable } from '../../core/orders/order-status.ts'
import { signedInActorId } from '../../plugins/identity.ts'
import { loadCustomerOrder } from './customer-order.ts'
import { declineNotice } from './decline-notice.ts'
import { renderNotFound, shopPage } from './shop-page.ts'

/** What a refused submission on the order page shows back: a field-less
 * refusal beside the "Message the seller" form that tripped it — one order
 * can hold a form per fulfillment, so the notice names which one. */
export type OrderPageState = {
  formError?: string
  formErrorFulfillmentId?: FulfillmentId
}

/**
 * The order page a URL names, carrying one form's refused submission when it
 * has one. Null when the id names nothing this customer may see, the same
 * 404 a fresh `GET` answers.
 */
export async function renderOrderPage(
  shop: FastifyInstance,
  request: FastifyRequest,
  reply: FastifyReply,
  orderId: OrderId,
  state: OrderPageState = {},
  status?: number,
): Promise<FastifyReply> {
  const found = await loadCustomerOrder(shop, request, orderId)
  if (found === null) return renderNotFound(reply)

  const { order, fulfillments, lastPayment } = found
  const rendered = status === undefined ? reply : reply.code(status)

  return rendered.render(
    'order',
    shopPage({
      title: `Order ${order.id}`,
      order,
      fulfillments,
      declineMessage: declineNotice(lastPayment),
      isUnpaid: isUnpaid(order.status),
      isPayable: isPayable(order.status, signedInActorId(request, 'customer') !== null),
      isCancellable: isCancellable(order.status),
      formError: state.formError,
      formErrorFulfillmentId: state.formErrorFulfillmentId,
    }),
  )
}
