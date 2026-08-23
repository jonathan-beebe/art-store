import path from 'node:path'
import Fastify, { type FastifyInstance } from 'fastify'
import fastifyCookie from '@fastify/cookie'
import fastifyFormbody from '@fastify/formbody'
import fastifyStatic from '@fastify/static'
import fastifyView from '@fastify/view'
import ejs from 'ejs'
import type pino from 'pino'
import type { Clock } from './clock.ts'
import type { AppConfig } from './config.ts'
import type { AppDatabase } from './db/database.ts'
import type { MagicLinkDelivery } from './delivery/magic-link-delivery.ts'
import { loggingOptions } from './logging.ts'
import { addErrorPage } from './plugins/error-pages.ts'
import { addEvents } from './plugins/events.ts'
import { addFlash } from './plugins/flash.ts'
import { addHealth } from './plugins/health.ts'
import { addIdentity } from './plugins/identity.ts'
import { addPageViewRollup } from './plugins/page-views.ts'
import { addSecurityHeaders } from './plugins/security-headers.ts'
import { addUnreadMessages } from './plugins/unread-messages.ts'
import { adminSite } from './sites/admin/index.ts'
import { authSite } from './sites/auth/index.ts'
import { sellerSite } from './sites/seller/index.ts'
import { shopSite } from './sites/shop/index.ts'

export type AppDependencies = {
  db: AppDatabase
  clock: Clock
  config: AppConfig
  magicLinkDelivery: MagicLinkDelivery
  /** Overrides where the request logger writes. Unset in the running app, so
   * Fastify's own logger writes to stdout; a test passes one to capture what
   * was logged. */
  loggerStream?: pino.DestinationStream
}

declare module 'fastify' {
  interface FastifyInstance {
    db: AppDatabase
    clock: Clock
    config: AppConfig
    magicLinkDelivery: MagicLinkDelivery
    // Flipped by server.ts before draining on SIGINT/SIGTERM, so /health
    // answers 503 while in-flight requests finish.
    draining: boolean
  }
}

const APP_ROOT = import.meta.dirname
const PUBLIC_ROOT = path.join(APP_ROOT, '..', 'public')

/**
 * The composition root. Everything that touches the world arrives as a
 * dependency, so a test builds the same app over an in-memory database and a
 * frozen clock.
 */
export function buildApp({
  db,
  clock,
  config,
  magicLinkDelivery,
  loggerStream,
}: AppDependencies): FastifyInstance {
  // trustProxy is what makes request.protocol and request.host read the
  // forwarded headers, so it is on only where a proxy is known to set them.
  const app = Fastify({
    ...loggingOptions(config, { stream: loggerStream }),
    trustProxy: config.trustProxy,
  })

  app.decorate('db', db)
  app.decorate('clock', clock)
  app.decorate('config', config)
  app.decorate('magicLinkDelivery', magicLinkDelivery)
  app.decorate('draining', false)

  app.register(fastifyCookie, { secret: config.cookieSecret })
  app.register(fastifyFormbody)
  app.register(fastifyStatic, { root: PUBLIC_ROOT, prefix: '/' })
  // A more specific prefix than the root registration above, so it wins for
  // anything under /uploads/ — the only place a browser-uploaded file is
  // served from. nosniff stops a browser from executing a served file as
  // script no matter what content type it guesses.
  app.register(fastifyStatic, {
    root: config.uploadsDir,
    prefix: '/uploads/',
    decorateReply: false,
    setHeaders: (reply) => {
      reply.header('X-Content-Type-Options', 'nosniff')
    },
  })

  // Templates are addressed from the app root, so a site layout reaches the
  // shared partials by the same path every other template uses.
  app.register(fastifyView, { engine: { ejs }, root: APP_ROOT, viewExt: 'ejs' })

  addErrorPage(app)
  addSecurityHeaders(app)
  addHealth(app)
  addFlash(app)
  addIdentity(app)
  addPageViewRollup(app)
  addUnreadMessages(app)
  addEvents(app)

  app.register(authSite)
  app.register(shopSite)
  app.register(sellerSite, { prefix: '/seller' })
  app.register(adminSite, { prefix: '/admin' })

  return app
}
