import type { FastifyError, FastifyPluginCallback } from 'fastify'
import multipart from '@fastify/multipart'
import { csrfProtection } from '../../plugins/csrf.ts'
import { addNotFoundPage } from '../../plugins/error-pages.ts'
import { unreadEventsRoute } from '../../plugins/events.ts'
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
  const renderPage = addSiteRender(portal, {
    pages: 'sites/seller/views',
    layout: 'sites/seller/views/layout',
  })
  portal.register(multipart, {
    attachFieldsToBody: true,
    limits: { fileSize: MAX_IMAGE_UPLOAD_BYTES, files: 1 },
  })
  // After multipart: `attachFieldsToBody` populates `request.body` through a
  // `preValidation` hook of its own, and a hook registered here runs after
  // one a plugin registered earlier in this same body already added.
  portal.register(csrfProtection)
  portal.addHook('preHandler', countUnreadMessages('seller'))

  addNotFoundPage(portal, renderPage)

  // A file over the limit throws while the multipart body is still parsing, so
  // it never reaches a route: the listing form re-renders with a field error
  // rather than the generic error page. Rethrowing anything else hands it to
  // the root handler.
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
    guarded.register(unreadEventsRoute('seller'))

    guardedDone()
  })

  done()
}
