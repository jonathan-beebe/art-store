import { test } from 'node:test'
import assert from 'node:assert/strict'
import Fastify from 'fastify'
import { rootPlugin } from './root-plugin.ts'

declare module 'fastify' {
  interface FastifyInstance {
    marker: string
  }
}

test('a decorator added inside the plugin lands on the root instance', async (t) => {
  const app = Fastify({ logger: false })
  t.after(() => app.close())

  await app.register(
    rootPlugin({ name: 'marker' }, (instance) => {
      instance.decorate('marker', 'root')
    }),
  )

  assert.equal(app.marker, 'root')
})

test('the plugin is named in the tree by its meta name', async (t) => {
  const app = Fastify({ logger: false })
  t.after(() => app.close())

  await app.register(rootPlugin({ name: 'securityHeaders' }, () => undefined))
  await app.ready()

  assert.match(app.printPlugins(), /securityHeaders/)
})

test('a dependency that was never registered fails the boot', async (t) => {
  const app = Fastify({ logger: false })
  t.after(() => app.close())

  app.register(rootPlugin({ name: 'needsCookies', dependencies: ['@fastify/cookie'] }, () => undefined))

  await assert.rejects(async () => await app.ready(), /@fastify\/cookie/)
})
