import { test } from 'node:test'
import assert from 'node:assert/strict'
import Fastify, { type FastifyInstance } from 'fastify'
import fastifyCookie from '@fastify/cookie'
import { addFlash } from './flash.ts'

const COOKIE_SECRET = 'flash-test-cookie-secret'

async function buildFlashApp(): Promise<FastifyInstance> {
  const app = Fastify({ logger: false })
  await app.register(fastifyCookie, { secret: COOKIE_SECRET })
  addFlash(app)

  app.post('/set', (request, reply) => {
    reply.setFlash(request.body as Record<string, string>)
    return reply.send('set')
  })
  app.get('/take', (_request, reply) => reply.send(reply.takeFlash()))

  await app.ready()
  return app
}

function flashCookie(response: { cookies: Array<{ name: string; value: string }> }): string {
  return response.cookies.find((cookie) => cookie.name === 'flash')?.value ?? ''
}

test('a flash set on one response is read by the next request', async (t) => {
  const app = await buildFlashApp()
  t.after(() => app.close())

  const set = await app.inject({
    method: 'POST',
    url: '/set',
    payload: { notice: 'Listing saved' },
  })
  const taken = await app.inject({
    method: 'GET',
    url: '/take',
    cookies: { flash: flashCookie(set) },
  })

  assert.deepEqual(taken.json(), { notice: 'Listing saved' })
})

test('reading a flash clears the cookie so it shows once', async (t) => {
  const app = await buildFlashApp()
  t.after(() => app.close())
  const set = await app.inject({ method: 'POST', url: '/set', payload: { alert: 'Link expired' } })

  const taken = await app.inject({
    method: 'GET',
    url: '/take',
    cookies: { flash: flashCookie(set) },
  })

  assert.equal(flashCookie(taken), '')
})

test('a request with no flash cookie reads an empty flash', async (t) => {
  const app = await buildFlashApp()
  t.after(() => app.close())

  const taken = await app.inject({ method: 'GET', url: '/take' })

  assert.deepEqual(taken.json(), {})
})

test('a cookie signed with another secret is ignored', async (t) => {
  const app = await buildFlashApp()
  t.after(() => app.close())
  const forged = Fastify()
  await forged.register(fastifyCookie, { secret: 'a-different-secret-entirely' })
  await forged.ready()

  const taken = await app.inject({
    method: 'GET',
    url: '/take',
    cookies: { flash: forged.signCookie(JSON.stringify({ notice: 'forged' })) },
  })

  assert.deepEqual(taken.json(), {})
  await forged.close()
})

test('a signed cookie holding something other than a flash is ignored', async (t) => {
  const app = await buildFlashApp()
  t.after(() => app.close())

  const taken = await app.inject({
    method: 'GET',
    url: '/take',
    cookies: { flash: app.signCookie('not json at all') },
  })

  assert.deepEqual(taken.json(), {})
})

test('flash keys the schema does not know are dropped', async (t) => {
  const app = await buildFlashApp()
  t.after(() => app.close())

  const taken = await app.inject({
    method: 'GET',
    url: '/take',
    cookies: { flash: app.signCookie(JSON.stringify({ notice: 'kept', surprise: 'dropped' })) },
  })

  assert.deepEqual(taken.json(), { notice: 'kept' })
})
