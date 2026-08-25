import { test } from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import path from 'node:path'
import { gunzipSync } from 'node:zlib'
import { buildStaticAssets } from './cli/build-assets.ts'
import { loadAssetManifest } from './http/asset-manifest.ts'
import { buildTestApp } from './test/build-test-app.ts'

const PUBLIC_DIR = path.join(import.meta.dirname, '..', 'public')

// Builds the hashed/compressed siblings the real public dir needs for these
// tests to exercise real files. Idempotent, so running it again here ahead of
// the suite is safe even when `npm run assets` already built them.
buildStaticAssets(PUBLIC_DIR)
const manifest = loadAssetManifest(PUBLIC_DIR)

/** Header value normalized to a string, whether light-my-request handed back
 * one value or several for the name. */
function header(value: string | string[] | undefined): string {
  return Array.isArray(value) ? value.join(', ') : String(value ?? '')
}

test('the hashed stylesheet is served gzip-encoded to a client that accepts it', async (t) => {
  const { app, close } = await buildTestApp()
  t.after(close)
  const onDisk = await readFile(path.join(PUBLIC_DIR, 'app.css'))

  const response = await app.inject({
    url: manifest['app.css'],
    headers: { 'accept-encoding': 'gzip' },
  })

  assert.equal(response.statusCode, 200)
  assert.equal(header(response.headers['content-encoding']), 'gzip')
  assert.match(header(response.headers.vary), /accept-encoding/i)
  assert.match(header(response.headers['content-type']), /text\/css/)
  assert.equal(header(response.headers['cache-control']), 'public, max-age=31536000, immutable')
  assert.deepEqual(gunzipSync(response.rawPayload), onDisk)
})

test('the hashed stylesheet is served brotli-encoded to a client that accepts it', async (t) => {
  const { app, close } = await buildTestApp()
  t.after(close)

  const response = await app.inject({
    url: manifest['app.css'],
    headers: { 'accept-encoding': 'br' },
  })

  assert.equal(header(response.headers['content-encoding']), 'br')
})

test('the hashed script is served gzip-encoded with the immutable cache policy', async (t) => {
  const { app, close } = await buildTestApp()
  t.after(close)

  const response = await app.inject({
    url: manifest['app.js'],
    headers: { 'accept-encoding': 'gzip' },
  })

  assert.equal(header(response.headers['content-encoding']), 'gzip')
  assert.equal(header(response.headers['cache-control']), 'public, max-age=31536000, immutable')
})

test('a client sending no Accept-Encoding gets the identity stylesheet, still varying on it', async (t) => {
  const { app, close } = await buildTestApp()
  t.after(close)
  const onDisk = await readFile(path.join(PUBLIC_DIR, 'app.css'))

  const response = await app.inject({ url: manifest['app.css'] })

  assert.equal(response.statusCode, 200)
  assert.equal(header(response.headers['content-encoding']), '')
  assert.deepEqual(response.rawPayload, onDisk)
  assert.match(header(response.headers.vary), /accept-encoding/i)
})

test('the unhashed /app.css keeps the five-minute cache policy and still varies on encoding', async (t) => {
  const { app, close } = await buildTestApp()
  t.after(close)

  const response = await app.inject({ url: '/app.css' })

  assert.equal(header(response.headers['cache-control']), 'public, max-age=300')
  assert.match(header(response.headers.vary), /accept-encoding/i)
})

test('a rendered page references the manifest paths for the stylesheet and script', async (t) => {
  const { app, close } = await buildTestApp()
  t.after(close)

  const response = await app.inject({ url: '/' })

  assert.equal(response.statusCode, 200)
  assert.ok(response.body.includes(`href="${manifest['app.css']}"`))
  assert.ok(response.body.includes(`src="${manifest['app.js']}"`))
})
