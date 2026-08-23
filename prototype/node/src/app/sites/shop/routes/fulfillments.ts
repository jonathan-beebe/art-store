import type { FastifyPluginCallback } from 'fastify'
import { z } from 'zod'
import { confirmDelivered } from '../../../actions/fulfillments/confirm-delivered.ts'
import { loadCustomerOrder } from '../customer-order.ts'
import { renderNotFound } from '../shop-page.ts'

const parameters = z.object({ fulfillmentId: z.coerce.number().int().positive() })

/**
 * The customer confirming a piece arrived, which is the step that releases the
 * seller's escrow. It stands in for carrier tracking in this prototype.
 */
export const fulfillmentRoutes: FastifyPluginCallback = (shop, _options, done) => {
  shop.post('/orders/:id/fulfillments/:fulfillmentId/delivered', async (request, reply) => {
    const found = await loadCustomerOrder(shop, request)
    const asked = parameters.safeParse(request.params)
    if (found === null || !asked.success) return renderNotFound(reply)

    const fulfillment = found.fulfillments.find((candidate) => candidate.id === asked.data.fulfillmentId)
    if (fulfillment === undefined || !fulfillment.canConfirmDelivery) return renderNotFound(reply)

    await confirmDelivered({ db: shop.db, clock: shop.clock }, fulfillment.id)
    reply.setFlash({ notice: 'Thank you — the seller has been paid out of escrow.' })

    return await reply.redirect(`/orders/${found.order.id}`)
  })

  done()
}
