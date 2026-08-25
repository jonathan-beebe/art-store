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
import { HASHED_ASSET_NAME, loadAssetManifest } from './http/asset-manifest.ts'
import { zodValidator } from './http/zod-type-provider.ts'
import { loggingOptions } from './logging.ts'
import { errorPages } from './plugins/error-pages.ts'
import { eventBus } from './plugins/events.ts'
import { flashCookie } from './plugins/flash.ts'
import { healthCheck } from './plugins/health.ts'
import { identityCookies } from './plugins/identity.ts'
import { pageViewRollup } from './plugins/page-views.ts'
import { placeholderImages } from './plugins/placeholder-images.ts'
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
  // trustProxy is what makes request.protocol, request.host, and request.ip
  // read forwarded headers rather than the raw socket. `trustedProxies` names
  // exactly which hops to believe, which is what request.ip needs to resist a
  // caller forging its own X-Forwarded-For; unset, Fastify trusts nothing
  // forwarded and reads the raw socket.
  const app = Fastify({
    ...loggingOptions(config, { stream: loggerStream }),
    trustProxy: config.trustedProxies ?? false,
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
  // A build names each stylesheet and script after its content hash and
  // pre-compresses it, so a hashed file is immutable for a year — a new
  // deploy ships a new name rather than overwriting this one.
  // preCompressed serves the matching .gz/.br sibling to a client that sent
  // Accept-Encoding rather than compressing on every request. app.css and
  // app.js keep those unhashed names as a fallback and stay on the
  // five-minute window: without a max-age the browser revalidates the
  // render-blocking stylesheet on every navigation, and the page paints
  // blank white for that round trip.
  app.register(fastifyStatic, {
    root: PUBLIC_ROOT,
    prefix: '/',
    maxAge: '5m',
    preCompressed: true,
    setHeaders: (reply, filePath) => {
      if (HASHED_ASSET_NAME.test(path.basename(filePath))) {
        reply.header('cache-control', 'public, max-age=31536000, immutable')
      }
    },
  })
  // A more specific prefix than the root registration above, so it wins for
  // anything under /uploads/ — the only place a browser-uploaded file is
  // served from. nosniff stops a browser from executing a served file as
  // script no matter what content type it guesses. An upload's UUID name is
  // never rewritten, so a served file can be cached as immutable.
  app.register(fastifyStatic, {
    root: config.uploadsDir,
    prefix: '/uploads/',
    decorateReply: false,
    maxAge: '7d',
    immutable: true,
    setHeaders: (reply) => {
      reply.header('X-Content-Type-Options', 'nosniff')
    },
  })

  // Templates are addressed from the app root, so a site layout reaches the
  // shared partials by the same path every other template uses.
  //
  // Caching is keyed off config.environment, not NODE_ENV, so a config that
  // runs like production (the test config included) caches like it too.
  // `production` turns on @fastify/view's LRU of compiled pages; `maxCache`
  // sizes it above the default 100 because each rendered template holds two
  // cache keys and the app has 71 templates. `options.cache` reaches EJS's
  // own compiler, which is what makes `include()` reuse its compiled-template
  // cache instead of re-reading and recompiling on every render. Development
  // stays uncached because `node --watch` does not watch `.ejs` files, so a
  // live template edit is only picked up through the re-read.
  const cachesTemplates = config.environment !== 'development'
  const assets = loadAssetManifest(PUBLIC_ROOT)
  app.register(fastifyView, {
    engine: { ejs },
    root: APP_ROOT,
    viewExt: 'ejs',
    production: cachesTemplates,
    maxCache: 500,
    options: { cache: cachesTemplates },
    defaultContext: { assets },
  })

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
  app.register(placeholderImages)

  // csrfProtection is registered inside each site rather than here: it reads
  // `request.body`, and `@fastify/multipart`'s `attachFieldsToBody` (seller's
  // own site) populates that itself through a `preValidation` hook of its
  // own. A hook the root adds always runs ahead of one a child registers,
  // whatever order they were written in — so registered here, the guard would
  // run before multipart had attached anything at all. Registered inside
  // each site, after that site's own body parser, it runs once that parser's
  // own hook (if it added one) already has.
  app.register(authSite)
  app.register(shopSite)
  app.register(sellerSite, { prefix: '/seller' })
  app.register(adminSite, { prefix: '/admin' })

  return app
}
