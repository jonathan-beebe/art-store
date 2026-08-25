import { readFileSync } from 'node:fs'
import path from 'node:path'

export const ASSET_MANIFEST_FILENAME = 'assets-manifest.json'

/** A stylesheet or script basename, hashed and optionally gzip/brotli
 * compressed: `app.<8 hex>.css`, `app.<8 hex>.js`, or either with a
 * `.gz`/`.br` suffix. */
export const HASHED_ASSET_NAME = /^app\.[0-9a-f]{8}\.(?:css|js)(?:\.gz|\.br)?$/

const FALLBACK_MANIFEST: AssetManifest = { 'app.css': '/app.css', 'app.js': '/app.js' }

export type AssetManifest = {
  'app.css': string
  'app.js': string
}

function isAssetManifest(value: unknown): value is AssetManifest {
  if (typeof value !== 'object' || value === null) return false
  const record = value as Record<string, unknown>
  return typeof record['app.css'] === 'string' && typeof record['app.js'] === 'string'
}

/**
 * The stylesheet and script paths a template should render. Reads
 * `assets-manifest.json` out of `publicDir`; a missing file, unreadable file,
 * malformed JSON, or JSON missing either string key falls back to the
 * unhashed paths so the app still renders against a public dir that hasn't
 * been built yet.
 */
export function loadAssetManifest(publicDir: string): AssetManifest {
  let contents: string
  try {
    contents = readFileSync(path.join(publicDir, ASSET_MANIFEST_FILENAME), 'utf8')
  } catch {
    return FALLBACK_MANIFEST
  }

  let parsed: unknown
  try {
    parsed = JSON.parse(contents)
  } catch {
    return FALLBACK_MANIFEST
  }

  return isAssetManifest(parsed) ? parsed : FALLBACK_MANIFEST
}
