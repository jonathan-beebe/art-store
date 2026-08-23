import { randomUUID } from 'node:crypto'
import { mkdir, writeFile } from 'node:fs/promises'
import path from 'node:path'
import { IMAGE_FORMAT_EXTENSIONS, type ImageFormat } from '../../core/listings/image-format.ts'

/** The multipart size limit an uploaded listing image is held to. Passed to
 * `@fastify/multipart`'s `limits.fileSize` and stated in the form's help text. */
export const MAX_IMAGE_UPLOAD_BYTES = 5 * 1024 * 1024
export const MAX_IMAGE_UPLOAD_MB = MAX_IMAGE_UPLOAD_BYTES / (1024 * 1024)

/**
 * Writes an uploaded listing image to `uploadsDir` under a name nothing else
 * can collide with, and returns the path a listing's `imagePath` column
 * stores — relative to the public root, so it doubles as the `<img src>`.
 * The extension comes from `format` — the image's own sniffed bytes, never
 * the browser's filename.
 */
export async function saveUploadedListingImage(
  uploadsDir: string,
  buffer: Buffer,
  format: ImageFormat,
): Promise<string> {
  await mkdir(uploadsDir, { recursive: true })
  const savedName = `${randomUUID()}.${IMAGE_FORMAT_EXTENSIONS[format]}`
  await writeFile(path.join(uploadsDir, savedName), buffer)

  return `/uploads/${savedName}`
}
