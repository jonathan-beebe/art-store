import { test } from 'node:test'
import assert from 'node:assert/strict'
import { buildTestApp } from '../../../test/build-test-app.ts'

test('the admin home page renders in the admin layout', async (t) => {
  const { app, close } = await buildTestApp()
  t.after(close)

  const response = await app.inject({ method: 'GET', url: '/admin' })

  assert.equal(response.statusCode, 200)
  assert.match(response.headers['content-type'] ?? '', /text\/html/)
  assert.match(response.body, /<title>Overview — Admin<\/title>/)
  assert.match(response.body, /href="\/app\.css"/)
})
