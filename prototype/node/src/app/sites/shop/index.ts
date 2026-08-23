import type { FastifyPluginCallback } from 'fastify'
import { addSiteRender } from '../../plugins/site-render.ts'
import { homeRoutes } from './routes/home.ts'

export const shopSite: FastifyPluginCallback = (shop, _options, done) => {
  addSiteRender(shop, { pages: 'sites/shop/views', layout: 'sites/shop/views/layout' })

  shop.register(homeRoutes)

  done()
}
