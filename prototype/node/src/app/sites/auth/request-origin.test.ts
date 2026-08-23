import { test } from 'node:test'
import assert from 'node:assert/strict'
import type { FastifyRequest } from 'fastify'
import { requestOrigin, magicLinkUrl } from './request-origin.ts'

test('the origin is the scheme and host the request arrived on', () => {
  const request = { protocol: 'http', host: 'localhost:4000' } as unknown as FastifyRequest

  assert.equal(requestOrigin(request), 'http://localhost:4000')
})

test('a magic link url is that origin plus /auth/magic/<token>', () => {
  const request = { protocol: 'http', host: 'localhost:4000' } as unknown as FastifyRequest

  assert.equal(magicLinkUrl(request, 'abc123'), 'http://localhost:4000/auth/magic/abc123')
})

test('a request that arrived on a different host builds a link on that host', () => {
  const request = { protocol: 'https', host: 'art-store.example.com' } as unknown as FastifyRequest

  assert.equal(magicLinkUrl(request, 'abc123'), 'https://art-store.example.com/auth/magic/abc123')
})
