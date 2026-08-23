import { randomUUID } from 'node:crypto'
import { mkdir, writeFile } from 'node:fs/promises'
import path from 'node:path'

const KNOWN_EXTENSIONS: Readonly<Record<string, string>> = {
  'image/png': 'png',
  'image/jpeg': 'jpg',
  'image/webp': 'webp',
  'image/gif': 'gif',
  'image/svg+xml': 'svg',
}

const DEFAULT_EXTENSION = 'bin'

/** The file extension an uploaded image is written under: the content type
 * when it names one, otherwise the extension of the filename the browser sent. */
export function extensionForImage(mimetype: string, filename: string): string {
  const fromFilename = path.extname(filename).replace(/^\./, '')

  return KNOWN_EXTENSIONS[mimetype] ?? (fromFilename === '' ? DEFAULT_EXTENSION : fromFilename)
}

/**
 * Writes an uploaded listing image to `uploadsDir` under a name nothing else
 * can collide with, and returns the path a listing's `imagePath` column
 * stores — relative to the public root, so it doubles as the `<img src>`.
 */
export async function saveUploadedListingImage(
  uploadsDir: string,
  buffer: Buffer,
  mimetype: string,
  filename: string,
): Promise<string> {
  await mkdir(uploadsDir, { recursive: true })
  const savedName = `${randomUUID()}.${extensionForImage(mimetype, filename)}`
  await writeFile(path.join(uploadsDir, savedName), buffer)

  return `/uploads/${savedName}`
}
