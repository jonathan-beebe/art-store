import type { FastifyPluginCallback } from 'fastify'
import multipart from '@fastify/multipart'
import { addSiteRender } from '../../plugins/site-render.ts'
import { requireSeller } from '../../plugins/identity.ts'
import { signInRoutes } from '../auth/sign-in-routes.ts'
import { earningsRoutes } from './routes/earnings.ts'
import { homeRoutes } from './routes/home.ts'
import { listingsRoutes } from './routes/listings.ts'
import { notificationsRoutes } from './routes/notifications.ts'
import { ordersRoutes } from './routes/orders.ts'

export const sellerSite: FastifyPluginCallback = (portal, _options, done) => {
  addSiteRender(portal, { pages: 'sites/seller/views', layout: 'sites/seller/views/layout' })
  portal.register(multipart, { attachFieldsToBody: true })

  portal.register(signInRoutes({ actorType: 'seller' }))

  portal.register((guarded, _guardedOptions, guardedDone) => {
    guarded.addHook('preHandler', requireSeller)

    guarded.register(homeRoutes)
    guarded.register(listingsRoutes)
    guarded.register(ordersRoutes)
    guarded.register(earningsRoutes)
    guarded.register(notificationsRoutes)

    guardedDone()
  })

  done()
}
