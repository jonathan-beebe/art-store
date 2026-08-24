import type { FastifyPluginCallback } from 'fastify'
import { csrfProtection } from '../../plugins/csrf.ts'
import { addNotFoundPage } from '../../plugins/error-pages.ts'
import { addSiteRender } from '../../plugins/site-render.ts'
import { countUnreadMessages } from '../../plugins/unread-messages.ts'
import { signInRoutes } from '../auth/sign-in-routes.ts'
import { customerAccountView } from './customer-account-view.ts'
import { storefrontRoutes } from './storefront.ts'

export const shopSite: FastifyPluginCallback = (shop, _options, done) => {
  const renderPage = addSiteRender(shop, {
    pages: 'sites/shop/views',
    layout: 'sites/shop/views/layout',
  })
  shop.register(csrfProtection)
  shop.addHook('preHandler', countUnreadMessages('customer'))

  // The storefront has no prefix, so this is also the page for any url the
  // whole app does not serve. Nothing about it resolves a customer: a mistyped
  // url must not mint an identity.
  addNotFoundPage(shop, renderPage)

  shop.register(signInRoutes({ actorType: 'customer', accountView: customerAccountView }))
  shop.register(storefrontRoutes)

  done()
}
