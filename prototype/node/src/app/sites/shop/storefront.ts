import type { FastifyPluginCallback } from 'fastify'
import { resolveCustomerIdentity } from '../../plugins/identity.ts'
import { homeRoutes } from './routes/home.ts'

/**
 * The browsing half of the storefront. Every page under here has a customer
 * behind it, anonymous until an address is verified, so favorites, a cart, and
 * a guest order always have an owner. The sign-in pages sit outside this
 * plugin: asking for a link must not mint an identity.
 */
export const storefrontRoutes: FastifyPluginCallback = (storefront, _options, done) => {
  storefront.addHook('preHandler', resolveCustomerIdentity)

  storefront.register(homeRoutes)

  done()
}
