import { createHash } from 'node:crypto'
import { readdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { brotliCompressSync, constants, gzipSync } from 'node:zlib'
import { ASSET_MANIFEST_FILENAME, HASHED_ASSET_NAME, type AssetManifest } from '../http/asset-manifest.ts'

const SOURCE_ASSET_NAMES = ['app.css', 'app.js'] as const

/** The first 8 hex characters of `content`'s sha256, used as the hashed
 * asset's filename fragment. */
function shortHash(content: Buffer): string {
  return createHash('sha256').update(content).digest('hex').slice(0, 8)
}

/** Deletes every file already matching `HASHED_ASSET_NAME`, so a build never
 * leaves an earlier run's hashed copy — or its `.gz`/`.br` siblings — behind
 * once the source content has moved on to a new hash. */
function removeStaleHashedAssets(publicDir: string): void {
  for (const entry of readdirSync(publicDir)) {
    if (HASHED_ASSET_NAME.test(entry)) rmSync(path.join(publicDir, entry))
  }
}

/** Writes the hashed copy of one source asset plus its gzip and brotli
 * siblings, and returns the hashed file's URL path. */
function writeHashedAsset(publicDir: string, sourceName: string): string {
  const content = readFileSync(path.join(publicDir, sourceName))
  const ext = path.extname(sourceName)
  const hashedName = `${path.basename(sourceName, ext)}.${shortHash(content)}${ext}`

  writeFileSync(path.join(publicDir, hashedName), content)
  writeFileSync(
    path.join(publicDir, `${hashedName}.gz`),
    gzipSync(content, { level: constants.Z_BEST_COMPRESSION }),
  )
  writeFileSync(
    path.join(publicDir, `${hashedName}.br`),
    brotliCompressSync(content, { params: { [constants.BROTLI_PARAM_SIZE_HINT]: content.length } }),
  )

  return `/${hashedName}`
}

/**
 * Hashes `app.css` and `app.js` in `publicDir`, writes gzip and brotli
 * siblings of each hashed copy, records the mapping from plain name to
 * hashed URL path in `assets-manifest.json`, and returns that mapping.
 * Removes any hashed assets an earlier build left behind first, so a stale
 * output never lingers alongside the fresh one.
 */
export function buildStaticAssets(publicDir: string): AssetManifest {
  removeStaleHashedAssets(publicDir)

  const manifest = Object.fromEntries(
    SOURCE_ASSET_NAMES.map((name) => [name, writeHashedAsset(publicDir, name)]),
  ) as AssetManifest

  writeFileSync(path.join(publicDir, ASSET_MANIFEST_FILENAME), JSON.stringify(manifest))

  return manifest
}

/** Builds the app's real public dir. Importable, so a test can call
 * `buildStaticAssets` directly against a temp dir instead. */
export async function main(): Promise<void> {
  buildStaticAssets(path.join(import.meta.dirname, '..', '..', 'public'))
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  await main()
}
