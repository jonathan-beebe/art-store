import { test } from 'node:test'
import assert from 'node:assert/strict'
import { createHash } from 'node:crypto'
import { mkdtemp, readdir, readFile, rm, writeFile } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { brotliDecompressSync, gunzipSync } from 'node:zlib'
import { buildStaticAssets } from './build-assets.ts'

/** Repeated enough that gzip and brotli both shrink it. */
const CSS_CONTENT = '.button-primary { color: #1a2b3c; background: #f0f0f0; padding: 4px 8px; }\n'.repeat(50)
const JS_CONTENT = 'console.log("hello from the storefront bundle");\n'.repeat(50)

/** A public dir seeded with the two source assets, removed after the test. */
async function seededPublicDir(t: import('node:test').TestContext): Promise<string> {
  const dir = await mkdtemp(path.join(tmpdir(), 'art-store-build-assets-'))
  t.after(() => rm(dir, { recursive: true, force: true }))
  await writeFile(path.join(dir, 'app.css'), CSS_CONTENT)
  await writeFile(path.join(dir, 'app.js'), JS_CONTENT)
  return dir
}

/** The first 8 hex characters of the content's sha256, matching the hash the
 * build derives its hashed filenames from. */
function shortHash(content: string): string {
  return createHash('sha256').update(content).digest('hex').slice(0, 8)
}

test('emits hashed copies named after the first 8 hex characters of the content hash', async (t) => {
  const dir = await seededPublicDir(t)
  const cssHash = shortHash(CSS_CONTENT)
  const jsHash = shortHash(JS_CONTENT)

  buildStaticAssets(dir)

  const hashedCss = await readFile(path.join(dir, `app.${cssHash}.css`), 'utf8')
  const hashedJs = await readFile(path.join(dir, `app.${jsHash}.js`), 'utf8')
  assert.equal(hashedCss, CSS_CONTENT)
  assert.equal(hashedJs, JS_CONTENT)
})

test('emits gzip and brotli siblings that decompress back to the original content', async (t) => {
  const dir = await seededPublicDir(t)
  const cssHash = shortHash(CSS_CONTENT)
  const jsHash = shortHash(JS_CONTENT)

  buildStaticAssets(dir)

  const cssGz = await readFile(path.join(dir, `app.${cssHash}.css.gz`))
  const cssBr = await readFile(path.join(dir, `app.${cssHash}.css.br`))
  const jsGz = await readFile(path.join(dir, `app.${jsHash}.js.gz`))
  const jsBr = await readFile(path.join(dir, `app.${jsHash}.js.br`))

  assert.equal(gunzipSync(cssGz).toString('utf8'), CSS_CONTENT)
  assert.equal(brotliDecompressSync(cssBr).toString('utf8'), CSS_CONTENT)
  assert.equal(gunzipSync(jsGz).toString('utf8'), JS_CONTENT)
  assert.equal(brotliDecompressSync(jsBr).toString('utf8'), JS_CONTENT)
})

test('writes a manifest mapping the plain names to the hashed URL paths, and returns the same mapping', async (t) => {
  const dir = await seededPublicDir(t)
  const cssHash = shortHash(CSS_CONTENT)
  const jsHash = shortHash(JS_CONTENT)

  const returned = buildStaticAssets(dir)

  const manifest: unknown = JSON.parse(await readFile(path.join(dir, 'assets-manifest.json'), 'utf8'))
  const expected = { 'app.css': `/app.${cssHash}.css`, 'app.js': `/app.${jsHash}.js` }
  assert.deepEqual(manifest, expected)
  assert.deepEqual(returned, expected)
})

test('a rerun over unchanged content produces the same hashed names', async (t) => {
  const dir = await seededPublicDir(t)

  const first = buildStaticAssets(dir)
  const second = buildStaticAssets(dir)

  assert.deepEqual(first, second)
})

test('removes stale hashed outputs from an earlier build before writing the fresh ones', async (t) => {
  const dir = await seededPublicDir(t)
  const staleNames = ['app.deadbeef.css', 'app.deadbeef.css.gz', 'app.deadbeef.css.br', 'app.deadbeef.js']
  for (const name of staleNames) await writeFile(path.join(dir, name), 'stale')

  buildStaticAssets(dir)

  const entries = await readdir(dir)
  for (const name of staleNames) assert.ok(!entries.includes(name), `${name} should have been removed`)

  const cssHash = shortHash(CSS_CONTENT)
  const jsHash = shortHash(JS_CONTENT)
  assert.ok(entries.includes(`app.${cssHash}.css`))
  assert.ok(entries.includes(`app.${jsHash}.js`))
  assert.ok(entries.includes('app.css'))
  assert.ok(entries.includes('app.js'))
})

test('leaves the original app.css and app.js files unmodified', async (t) => {
  const dir = await seededPublicDir(t)

  buildStaticAssets(dir)

  assert.equal(await readFile(path.join(dir, 'app.css'), 'utf8'), CSS_CONTENT)
  assert.equal(await readFile(path.join(dir, 'app.js'), 'utf8'), JS_CONTENT)
})
