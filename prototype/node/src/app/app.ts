import path from 'node:path'
import Fastify, { type FastifyInstance } from 'fastify'
import fastifyCookie from '@fastify/cookie'
import fastifyFormbody from '@fastify/formbody'
import fastifyStatic from '@fastify/static'
import fastifyView from '@fastify/view'
import ejs from 'ejs'
import type { Clock } from './clock.ts'
import type { AppConfig } from './config.ts'
import type { AppDatabase } from './db/database.ts'
import { addFlash } from './plugins/flash.ts'
import { adminSite } from './sites/admin/index.ts'
import { sellerSite } from './sites/seller/index.ts'
import { shopSite } from './sites/shop/index.ts'

export type AppDependencies = {
  db: AppDatabase
  clock: Clock
  config: AppConfig
}

declare module 'fastify' {
  interface FastifyInstance {
    db: AppDatabase
    clock: Clock
    config: AppConfig
  }
}

const APP_ROOT = import.meta.dirname
const PUBLIC_ROOT = path.join(APP_ROOT, '..', 'public')

/**
 * The composition root. Everything that touches the world arrives as a
 * dependency, so a test builds the same app over an in-memory database and a
 * frozen clock.
 */
export function buildApp({ db, clock, config }: AppDependencies): FastifyInstance {
  const app = Fastify({ logger: { level: config.logLevel } })

  app.decorate('db', db)
  app.decorate('clock', clock)
  app.decorate('config', config)

  app.register(fastifyCookie, { secret: config.cookieSecret })
  app.register(fastifyFormbody)
  app.register(fastifyStatic, { root: PUBLIC_ROOT, prefix: '/' })

  // Templates are addressed from the app root, so a site layout reaches the
  // shared partials by the same path every other template uses.
  app.register(fastifyView, { engine: { ejs }, root: APP_ROOT, viewExt: 'ejs' })

  addFlash(app)

  app.register(shopSite)
  app.register(sellerSite, { prefix: '/seller' })
  app.register(adminSite, { prefix: '/admin' })

  return app
}
