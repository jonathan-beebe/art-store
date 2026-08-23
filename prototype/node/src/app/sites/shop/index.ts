import type { FastifyPluginCallback } from 'fastify'
import { addSiteRender } from '../../plugins/site-render.ts'
import { signInRoutes } from '../auth/sign-in-routes.ts'
import { storefrontRoutes } from './storefront.ts'

export const shopSite: FastifyPluginCallback = (shop, _options, done) => {
  addSiteRender(shop, { pages: 'sites/shop/views', layout: 'sites/shop/views/layout' })

  shop.register(signInRoutes({ actorType: 'customer' }))
  shop.register(storefrontRoutes)

  done()
}
