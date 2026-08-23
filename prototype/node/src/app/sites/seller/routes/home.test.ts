import { test } from 'node:test'
import assert from 'node:assert/strict'
import { buildTestApp } from '../../../test/build-test-app.ts'

test('the seller portal home page renders in the portal layout', async (t) => {
  const { app, close } = await buildTestApp()
  t.after(close)

  const response = await app.inject({ method: 'GET', url: '/seller' })

  assert.equal(response.statusCode, 200)
  assert.match(response.headers['content-type'] ?? '', /text\/html/)
  assert.match(response.body, /<title>Overview — Seller portal<\/title>/)
  assert.match(response.body, /href="\/app\.css"/)
})
