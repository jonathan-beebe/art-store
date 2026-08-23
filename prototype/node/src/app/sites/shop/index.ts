import type { FastifyPluginCallback } from 'fastify'
import { addSiteRender } from '../../plugins/site-render.ts'
import { countUnreadMessages } from '../../plugins/unread-messages.ts'
import { signInRoutes } from '../auth/sign-in-routes.ts'
import { customerAccountView } from './customer-account-view.ts'
import { storefrontRoutes } from './storefront.ts'

export const shopSite: FastifyPluginCallback = (shop, _options, done) => {
  addSiteRender(shop, { pages: 'sites/shop/views', layout: 'sites/shop/views/layout' })
  shop.addHook('preHandler', countUnreadMessages('customer'))

  shop.register(signInRoutes({ actorType: 'customer', accountView: customerAccountView }))
  shop.register(storefrontRoutes)

  done()
}
