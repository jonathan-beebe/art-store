import type { FastifyPluginCallback } from 'fastify'
import { resolveCustomerIdentity } from '../../plugins/identity.ts'
import { cartRoutes } from './routes/carts.ts'
import { checkoutRoutes } from './routes/checkout.ts'
import { favoriteRoutes } from './routes/favorites.ts'
import { fulfillmentRoutes } from './routes/fulfillments.ts'
import { homeRoutes } from './routes/home.ts'
import { listingRoutes } from './routes/listings.ts'
import { notificationRoutes } from './routes/notifications.ts'
import { orderPaymentRoutes } from './routes/order-payments.ts'
import { orderRoutes } from './routes/orders.ts'

/**
 * The browsing half of the storefront. Every page under here has a customer
 * behind it, anonymous until an address is verified, so favorites, a cart, and
 * a guest order always have an owner. The sign-in pages sit outside this
 * plugin: asking for a link must not mint an identity.
 */
export const storefrontRoutes: FastifyPluginCallback = (storefront, _options, done) => {
  storefront.addHook('preHandler', resolveCustomerIdentity)

  storefront.register(homeRoutes)
  storefront.register(listingRoutes)
  storefront.register(favoriteRoutes)
  storefront.register(cartRoutes)
  storefront.register(checkoutRoutes)
  storefront.register(orderRoutes)
  storefront.register(orderPaymentRoutes)
  storefront.register(fulfillmentRoutes)
  storefront.register(notificationRoutes)

  done()
}
