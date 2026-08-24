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
import { zodValidator } from './http/zod-type-provider.ts'
import { loggingOptions } from './logging.ts'
import { errorPages } from './plugins/error-pages.ts'
import { eventBus } from './plugins/events.ts'
import { flashCookie } from './plugins/flash.ts'
import { healthCheck } from './plugins/health.ts'
import { identityCookies } from './plugins/identity.ts'
import { pageViewRollup } from './plugins/page-views.ts'
import { requestLog } from './plugins/request-log.ts'
import { securityHeaders } from './plugins/security-headers.ts'
import { unreadMessages } from './plugins/unread-messages.ts'
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

  // Every route declares its params, query, and body as zod schemas; this is
  // what runs them, and what puts the parsed value on the request in place of
  // the raw one.
  app.setValidatorCompiler(zodValidator)

  app.decorate('db', db)
  app.decorate('clock', clock)
  app.decorate('config', config)
  app.decorate('magicLinkDelivery', magicLinkDelivery)
  app.decorate('draining', false)

  app.register(fastifyCookie, { secret: config.cookieSecret })
  // Ahead of the static and site plugins: a route inherits the root's hooks as
  // they stand when its own context is built, so a hook added after them would
  // never see the requests they answer.
  app.register(requestLog)
  // Kept in place of a `URLSearchParams` parser of its own. Every form here is
  // flat and would survive the swap, but this parser also decides what a
  // repeated or bracketed field name means, and deciding that by hand is a
  // larger question than the few lines it would save.
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

  // Every plugin below decorates or hooks the root instance, and a site
  // inherits the root's hooks as they stand when its own context is built —
  // so all of them are registered before the first site. Order within the
  // group is the order their hooks run in.
  app.register(errorPages)
  app.register(securityHeaders)
  app.register(flashCookie)
  app.register(identityCookies)
  app.register(pageViewRollup)
  app.register(unreadMessages)
  app.register(eventBus)
  app.register(healthCheck)

  app.register(authSite)
  app.register(shopSite)
  app.register(sellerSite, { prefix: '/seller' })
  app.register(adminSite, { prefix: '/admin' })

  return app
}
