import { crc32 } from 'node:zlib'

const SIZE = 800
const LABEL_LENGTH = 40

const HTML_ESCAPES: Record<string, string> = {
  '&': '&amp;',
  '<': '&lt;',
  '>': '&gt;',
  '"': '&quot;',
  "'": '&#39;',
}

function escapeHtml(text: string): string {
  return text.replace(/[&<>"']/g, (char) => HTML_ESCAPES[char] ?? char)
}

function shapeAt(index: number, seed: number, hue: number, secondHue: number): string {
  const step = (seed >>> (index * 3)) & 0xffff
  const x = 100 + ((step * 7) % (SIZE - 200))
  const y = 100 + ((step * 13) % (SIZE - 300))
  const size = 80 + ((step * 3) % 220)
  const fillHue = index % 2 === 0 ? hue : secondHue

  return index % 3 === 0
    ? `<circle cx="${x}" cy="${y}" r="${size}" fill="hsl(${fillHue} 55% 55% / 0.45)"/>`
    : `<rect x="${x}" y="${y}" width="${size}" height="${size}" rx="24" fill="hsl(${fillHue} 50% 50% / 0.4)"/>`
}

function shapes(seed: number, hue: number, secondHue: number): string {
  const count = 3 + (seed % 4)
  return Array.from({ length: count }, (_, index) => shapeAt(index, seed, hue, secondHue)).join('')
}

// The palette and composition derive from the title's crc32 so the same
// listing always renders the same picture and different listings differ.
export function placeholderImageSvg(title: string): string {
  const seed = crc32(title)
  const hue = seed % 360
  const secondHue = (hue + 140 + ((seed >>> 8) % 80)) % 360
  const label = escapeHtml(title.slice(0, LABEL_LENGTH))

  return (
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${SIZE} ${SIZE}" width="${SIZE}" height="${SIZE}" role="img" aria-label="${label}">` +
    `<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="hsl(${hue} 60% 88%)"/><stop offset="1" stop-color="hsl(${secondHue} 55% 80%)"/></linearGradient></defs>` +
    `<rect width="${SIZE}" height="${SIZE}" fill="url(#g)"/>` +
    shapes(seed, hue, secondHue) +
    `<text x="40" y="760" font-family="ui-sans-serif, system-ui, sans-serif" font-size="28" fill="hsl(${hue} 40% 25%)">${label}</text>` +
    `</svg>`
  )
}

/** The route a listing's title's generated placeholder renders at. A slash in
 * the title is encoded along with everything else, so the router reads it as
 * part of `:title` rather than as a path separator. */
export function placeholderImagePath(title: string): string {
  return `/placeholders/${encodeURIComponent(title)}`
}

export function listingImageSource(imagePath: string | null, title: string): string {
  return imagePath ?? placeholderImagePath(title)
}
