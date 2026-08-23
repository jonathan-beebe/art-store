import type { FastifyInstance, FastifyRequest } from 'fastify'
import { cartLineTotal } from '../../core/cart/cart-line.ts'
import type { Cents } from '../../core/money.ts'
import type { OrderItem } from '../../db/commerce-schema.ts'
import {
  findCustomerOrder,
  type CustomerOrder,
  type OrderFulfillment,
} from './queries/find-customer-order.ts'
import { storefrontCustomer } from './storefront-customer.ts'

/** An order item priced for display: what the line comes to at its quantity. */
export type OrderItemView = OrderItem & { lineTotalCents: Cents }

export type OrderFulfillmentView = Omit<OrderFulfillment, 'items'> & {
  items: readonly OrderItemView[]
}

export type CustomerOrderView = Omit<CustomerOrder, 'fulfillments'> & {
  fulfillments: readonly OrderFulfillmentView[]
}

/** Where a page under `/orders/:id` sends a visitor it will not serve. */
export function customerOrderPath({ id }: { id: number }): string {
  return `/orders/${id}`
}

function withLineTotal(item: OrderItem): OrderItemView {
  return { ...item, lineTotalCents: cartLineTotal(item) }
}

/**
 * The order the URL names, read as the customer behind the request. Null covers
 * an id that names nothing and an id that names someone else's order alike, so
 * every page over it answers the same way. Each item carries its own
 * `lineTotalCents`, priced through `cartLineTotal`, so a page never multiplies.
 */
export async function loadCustomerOrder(
  app: FastifyInstance,
  request: FastifyRequest,
  orderId: number,
): Promise<CustomerOrderView | null> {
  const found = await findCustomerOrder(app.db, {
    orderId,
    customerId: storefrontCustomer(request).id,
  })
  if (found === null) return null

  return {
    ...found,
    fulfillments: found.fulfillments.map((fulfillment) => ({
      ...fulfillment,
      items: fulfillment.items.map(withLineTotal),
    })),
  }
}
