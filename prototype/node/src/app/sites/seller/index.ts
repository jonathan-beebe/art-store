import type { FastifyError, FastifyPluginCallback } from 'fastify'
import multipart from '@fastify/multipart'
import { addSiteRender } from '../../plugins/site-render.ts'
import { requireSeller } from '../../plugins/identity.ts'
import { countUnreadMessages } from '../../plugins/unread-messages.ts'
import { signInRoutes } from '../auth/sign-in-routes.ts'
import { earningsRoutes } from './routes/earnings.ts'
import { faqsRoutes } from './routes/faqs.ts'
import { homeRoutes } from './routes/home.ts'
import { listingsRoutes, renderOversizedImageForm } from './routes/listings.ts'
import { MAX_IMAGE_UPLOAD_BYTES } from './listing-image-upload.ts'
import { messagesRoutes } from './routes/messages.ts'
import { notificationsRoutes } from './routes/notifications.ts'
import { ordersRoutes } from './routes/orders.ts'

export const sellerSite: FastifyPluginCallback = (portal, _options, done) => {
  addSiteRender(portal, { pages: 'sites/seller/views', layout: 'sites/seller/views/layout' })
  portal.register(multipart, {
    attachFieldsToBody: true,
    limits: { fileSize: MAX_IMAGE_UPLOAD_BYTES, files: 1 },
  })
  portal.addHook('preHandler', countUnreadMessages('seller'))

  // Scoped to this site: a file over the limit throws while the multipart
  // body is still parsing, before @fastify/multipart's default JSON 413
  // would otherwise answer — the listing form re-renders with a field error
  // instead.
  portal.setErrorHandler(async (error: FastifyError, request, reply) => {
    if (error.code === 'FST_REQ_FILE_TOO_LARGE') return renderOversizedImageForm(request, reply)
    throw error
  })

  portal.register(signInRoutes({ actorType: 'seller' }))

  portal.register((guarded, _guardedOptions, guardedDone) => {
    guarded.addHook('preHandler', requireSeller)

    guarded.register(homeRoutes)
    guarded.register(listingsRoutes)
    guarded.register(ordersRoutes)
    guarded.register(earningsRoutes)
    guarded.register(notificationsRoutes)
    guarded.register(messagesRoutes)
    guarded.register(faqsRoutes)

    guardedDone()
  })

  done()
}
