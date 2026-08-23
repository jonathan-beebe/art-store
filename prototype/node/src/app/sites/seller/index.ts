import type { FastifyPluginCallback } from 'fastify'
import { addSiteRender } from '../../plugins/site-render.ts'
import { signInRoutes } from '../auth/sign-in-routes.ts'
import { homeRoutes } from './routes/home.ts'

export const sellerSite: FastifyPluginCallback = (portal, _options, done) => {
  addSiteRender(portal, { pages: 'sites/seller/views', layout: 'sites/seller/views/layout' })

  portal.register(signInRoutes({ actorType: 'seller' }))
  portal.register(homeRoutes)

  done()
}
