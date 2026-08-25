import { test } from 'node:test'
import assert from 'node:assert/strict'
import { mkdtemp, rm, writeFile } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { ASSET_MANIFEST_FILENAME, HASHED_ASSET_NAME, isAssetPath, loadAssetManifest } from './asset-manifest.ts'

const FALLBACK = { 'app.css': '/app.css', 'app.js': '/app.js' }

/** A fresh directory to read a manifest from, removed after the test. */
async function tempPublicDir(t: import('node:test').TestContext): Promise<string> {
  const dir = await mkdtemp(path.join(tmpdir(), 'art-store-asset-manifest-'))
  t.after(() => rm(dir, { recursive: true, force: true }))
  return dir
}

test('reads the manifest file and returns its mapping', async (t) => {
  const dir = await tempPublicDir(t)
  await writeFile(
    path.join(dir, ASSET_MANIFEST_FILENAME),
    JSON.stringify({ 'app.css': '/app.1a2b3c4d.css', 'app.js': '/app.5e6f7a8b.js' }),
  )

  assert.deepEqual(loadAssetManifest(dir), {
    'app.css': '/app.1a2b3c4d.css',
    'app.js': '/app.5e6f7a8b.js',
  })
})

test('a missing manifest file falls back to the unhashed names', async (t) => {
  const dir = await tempPublicDir(t)

  assert.deepEqual(loadAssetManifest(dir), FALLBACK)
})

test('malformed JSON falls back to the unhashed names', async (t) => {
  const dir = await tempPublicDir(t)
  await writeFile(path.join(dir, ASSET_MANIFEST_FILENAME), '{not json')

  assert.deepEqual(loadAssetManifest(dir), FALLBACK)
})

test('a manifest missing the app.js key falls back to the unhashed names', async (t) => {
  const dir = await tempPublicDir(t)
  await writeFile(path.join(dir, ASSET_MANIFEST_FILENAME), JSON.stringify({ 'app.css': '/app.1a2b3c4d.css' }))

  assert.deepEqual(loadAssetManifest(dir), FALLBACK)
})

test('a manifest whose app.js value is not a string falls back to the unhashed names', async (t) => {
  const dir = await tempPublicDir(t)
  await writeFile(
    path.join(dir, ASSET_MANIFEST_FILENAME),
    JSON.stringify({ 'app.css': '/app.1a2b3c4d.css', 'app.js': 42 }),
  )

  assert.deepEqual(loadAssetManifest(dir), FALLBACK)
})

test('HASHED_ASSET_NAME matches a hashed stylesheet and script, plain and compressed', () => {
  assert.match('app.1a2b3c4d.css', HASHED_ASSET_NAME)
  assert.match('app.1a2b3c4d.js', HASHED_ASSET_NAME)
  assert.match('app.1a2b3c4d.css.gz', HASHED_ASSET_NAME)
  assert.match('app.1a2b3c4d.js.br', HASHED_ASSET_NAME)
})

test('HASHED_ASSET_NAME does not match the unhashed names or a short or foreign hash', () => {
  assert.doesNotMatch('app.css', HASHED_ASSET_NAME)
  assert.doesNotMatch('app.js', HASHED_ASSET_NAME)
  assert.doesNotMatch('app.css.gz', HASHED_ASSET_NAME)
  assert.doesNotMatch('app.1a2b3c.css', HASHED_ASSET_NAME)
  assert.doesNotMatch('logo.1a2b3c4d.css', HASHED_ASSET_NAME)
})

test('isAssetPath matches the unhashed names, a hashed asset, its compressed siblings, and uploads', () => {
  for (const pathname of [
    '/app.css',
    '/app.js',
    '/app.1a2b3c4d.css',
    '/app.1a2b3c4d.js.gz',
    '/uploads/photo.png',
    '/uploads/nothing.png',
  ]) {
    assert.equal(isAssetPath(pathname), true, pathname)
  }
})

test('isAssetPath rejects a page route, a nested hashed-looking name, and the assets manifest itself', () => {
  for (const pathname of ['/', '/nothing-here', '/seller/account', '/foo/app.1a2b3c4d.css', '/assets-manifest.json']) {
    assert.equal(isAssetPath(pathname), false, pathname)
  }
})
