export const IMAGE_FORMATS = ['png', 'jpeg', 'gif', 'webp'] as const

export type ImageFormat = (typeof IMAGE_FORMATS)[number]

export const IMAGE_FORMAT_EXTENSIONS: Readonly<Record<ImageFormat, string>> = {
  png: 'png',
  jpeg: 'jpg',
  gif: 'gif',
  webp: 'webp',
}

export const IMAGE_FORMAT_CONTENT_TYPES: Readonly<Record<ImageFormat, string>> = {
  png: 'image/png',
  jpeg: 'image/jpeg',
  gif: 'image/gif',
  webp: 'image/webp',
}

const PNG_SIGNATURE = [0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]
const JPEG_SIGNATURE = [0xff, 0xd8, 0xff]
const GIF87A_SIGNATURE = [0x47, 0x49, 0x46, 0x38, 0x37, 0x61]
const GIF89A_SIGNATURE = [0x47, 0x49, 0x46, 0x38, 0x39, 0x61]
const RIFF_SIGNATURE = [0x52, 0x49, 0x46, 0x46]
const WEBP_SIGNATURE = [0x57, 0x45, 0x42, 0x50]
const WEBP_FORMAT_OFFSET = 8

function bytesStartWith(bytes: Uint8Array, signature: readonly number[], offset = 0): boolean {
  if (bytes.length < offset + signature.length) return false

  return signature.every((byte, index) => bytes[offset + index] === byte)
}

function isWebp(bytes: Uint8Array): boolean {
  return bytesStartWith(bytes, RIFF_SIGNATURE) && bytesStartWith(bytes, WEBP_SIGNATURE, WEBP_FORMAT_OFFSET)
}

/**
 * The image format the bytes themselves declare, read from their leading
 * magic bytes. A browser-supplied filename or `Content-Type` header names
 * nothing here — only the bytes decide. Returns null for anything else,
 * SVG (text, not a magic-byte format) included.
 */
export function sniffImageFormat(bytes: Uint8Array): ImageFormat | null {
  if (bytesStartWith(bytes, PNG_SIGNATURE)) return 'png'
  if (bytesStartWith(bytes, JPEG_SIGNATURE)) return 'jpeg'
  if (bytesStartWith(bytes, GIF87A_SIGNATURE) || bytesStartWith(bytes, GIF89A_SIGNATURE)) return 'gif'
  if (isWebp(bytes)) return 'webp'

  return null
}
