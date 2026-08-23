import { test } from 'node:test'
import assert from 'node:assert/strict'
import type { FastifyRequest } from 'fastify'
import { requestOrigin, magicLinkUrl } from './request-origin.ts'

function requestFrom(protocol: string, host: string, publicUrl: string | null): FastifyRequest {
  return { protocol, host, server: { config: { publicUrl } } } as unknown as FastifyRequest
}

test('the origin is the scheme and host the request arrived on', () => {
  assert.equal(requestOrigin(requestFrom('http', 'localhost:4000', null)), 'http://localhost:4000')
})

test('a magic link url is that origin plus /auth/magic/<token>', () => {
  const request = requestFrom('http', 'localhost:4000', null)

  assert.equal(magicLinkUrl(request, 'abc123'), 'http://localhost:4000/auth/magic/abc123')
})

test('a request that arrived on a different host builds a link on that host', () => {
  const request = requestFrom('https', 'art-store.example.com', null)

  assert.equal(magicLinkUrl(request, 'abc123'), 'https://art-store.example.com/auth/magic/abc123')
})

test('a configured public url wins over the host header the request carried', () => {
  const request = requestFrom('http', 'attacker.example', 'https://art-store.example.com')

  assert.equal(requestOrigin(request), 'https://art-store.example.com')
  assert.equal(magicLinkUrl(request, 'abc123'), 'https://art-store.example.com/auth/magic/abc123')
})
