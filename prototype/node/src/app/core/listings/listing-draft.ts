import { parseDollars, type Cents } from '../money.ts'
import type { ImageFormat } from './image-format.ts'

const LINE_LIMIT = 255
const DESCRIPTION_LIMIT = 5_000
const QUANTITY_LIMIT = 999
const DOLLARS_PATTERN = /^\d+(\.\d{1,2})?$/
const WHOLE_NUMBER_PATTERN = /^\d+$/

/** What an uploaded image sniffed as: a recognized format, `'unrecognized'`
 * for bytes that matched none of them, or `null`/`undefined` for no upload
 * at all. Never the browser's filename or `Content-Type` header. */
export type UploadedImageFormat = ImageFormat | 'unrecognized'

export type ListingDraftFields = {
  title?: string
  description?: string
  medium?: string
  dimensions?: string
  price?: string
  quantity?: string
  imageFormat?: UploadedImageFormat | null
}

export type ListingDraftErrors = Partial<
  Record<'title' | 'description' | 'medium' | 'dimensions' | 'price' | 'quantity' | 'image', string>
>

function lineError(value: string | undefined, limit: number, field: string): string | undefined {
  return (value ?? '').trim().length > limit ? `Keep the ${field} under ${limit} characters.` : undefined
}

function titleError(value: string | undefined): string | undefined {
  if ((value ?? '').trim().length === 0) {
    return 'Enter a title.'
  }
  return lineError(value, LINE_LIMIT, 'title')
}

function priceError(value: string | undefined): string | undefined {
  return DOLLARS_PATTERN.test((value ?? '').trim()) ? undefined : 'The price is an amount in dollars, like 249.00.'
}

function quantityError(value: string | undefined): string | undefined {
  const quantity = (value ?? '').trim()
  if (WHOLE_NUMBER_PATTERN.test(quantity) && Number(quantity) <= QUANTITY_LIMIT) {
    return undefined
  }
  return `The quantity is a whole number from 0 to ${QUANTITY_LIMIT}.`
}

function imageError(imageFormat: UploadedImageFormat | null | undefined): string | undefined {
  if (imageFormat === null || imageFormat === undefined || imageFormat !== 'unrecognized') {
    return undefined
  }
  return 'Upload an image file.'
}

export function listingDraftErrors(fields: ListingDraftFields): ListingDraftErrors {
  const entries: [keyof ListingDraftErrors, string | undefined][] = [
    ['title', titleError(fields.title)],
    ['description', lineError(fields.description, DESCRIPTION_LIMIT, 'description')],
    ['medium', lineError(fields.medium, LINE_LIMIT, 'medium')],
    ['dimensions', lineError(fields.dimensions, LINE_LIMIT, 'dimensions')],
    ['price', priceError(fields.price)],
    ['quantity', quantityError(fields.quantity)],
    ['image', imageError(fields.imageFormat)],
  ]

  return Object.fromEntries(entries.filter(([, message]) => message !== undefined)) as ListingDraftErrors
}

export type ListingDraft = {
  title: string
  description: string | null
  medium: string | null
  dimensions: string | null
  priceCents: Cents
  quantity: number
}

function written(value: string | undefined): string | null {
  const text = (value ?? '').trim()
  return text.length === 0 ? null : text
}

// Carries no status, slug, or image: those are the portal's to decide.
export function parseListingDraft(fields: ListingDraftFields): ListingDraft {
  return {
    title: (fields.title ?? '').trim(),
    description: written(fields.description),
    medium: written(fields.medium),
    dimensions: written(fields.dimensions),
    priceCents: parseDollars(fields.price ?? ''),
    quantity: Number(fields.quantity),
  }
}
