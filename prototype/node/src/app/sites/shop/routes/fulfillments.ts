import { z } from 'zod'
import { confirmDelivered } from '../../../actions/fulfillments/confirm-delivered.ts'
import { TransitionError } from '../../../core/transition-error.ts'
import { idValue } from '../../../http/request-schema.ts'
import type { ZodRoutes } from '../../../http/zod-type-provider.ts'
import { loadCustomerOrder } from '../customer-order.ts'
import { renderNotFound } from '../shop-page.ts'

const fulfillmentParams = z.object({ id: idValue, fulfillmentId: idValue })

/**
 * The customer confirming a piece arrived, which is the step that releases the
 * seller's escrow. It stands in for carrier tracking in this prototype.
 */
export const fulfillmentRoutes: ZodRoutes = (shop, _options, done) => {
  shop.post(
    '/orders/:id/fulfillments/:fulfillmentId/delivered',
    { schema: { params: fulfillmentParams } },
    async (request, reply) => {
      const found = await loadCustomerOrder(shop, request, request.params.id)
      if (found === null) return renderNotFound(reply)

      const fulfillment = found.fulfillments.find(
        (candidate) => candidate.id === request.params.fulfillmentId,
      )
      if (fulfillment === undefined) return renderNotFound(reply)

      try {
        await confirmDelivered({ db: shop.db, clock: shop.clock }, fulfillment.id)
      } catch (error) {
        if (!(error instanceof TransitionError)) throw error
        reply.setFlash({ alert: error.message })

        return await reply.redirect(`/orders/${found.order.id}`)
      }

      reply.setFlash({ notice: 'Thank you — the seller has been paid out of escrow.' })

      return await reply.redirect(`/orders/${found.order.id}`)
    },
  )

  done()
}
