import { isDollarAmount, parseDollars, type Cents } from '../money.ts'
import type { ImageFormat } from './image-format.ts'

const LINE_LIMIT = 255
const DESCRIPTION_LIMIT = 5_000
const QUANTITY_LIMIT = 999
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

/** A listing as the portal saves it. Carries no status, slug, or image: those
 * are the portal's to decide. */
export type ListingDraft = {
  title: string
  description: string | null
  medium: string | null
  dimensions: string | null
  priceCents: Cents
  quantity: number
}

export type ListingDraftResult =
  | { ok: true; value: ListingDraft }
  | { ok: false; errors: ListingDraftErrors }

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
  return isDollarAmount(value ?? '') ? undefined : 'The price is an amount in dollars, like 249.00.'
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

function draftErrors(fields: ListingDraftFields): ListingDraftErrors {
  const checked: readonly [keyof ListingDraftErrors, string | undefined][] = [
    ['title', titleError(fields.title)],
    ['description', lineError(fields.description, DESCRIPTION_LIMIT, 'description')],
    ['medium', lineError(fields.medium, LINE_LIMIT, 'medium')],
    ['dimensions', lineError(fields.dimensions, LINE_LIMIT, 'dimensions')],
    ['price', priceError(fields.price)],
    ['quantity', quantityError(fields.quantity)],
    ['image', imageError(fields.imageFormat)],
  ]

  const errors: ListingDraftErrors = {}
  for (const [field, message] of checked) {
    if (message !== undefined) errors[field] = message
  }

  return errors
}

function written(value: string | undefined): string | null {
  const text = (value ?? '').trim()
  return text.length === 0 ? null : text
}

/**
 * The submitted form as a draft, or every field that is wrong. The price is
 * checked and converted against one grammar (`isDollarAmount` and
 * `parseDollars` share it), so the amount the `ok` arm carries is one
 * `parseDollars` could not have refused.
 */
export function parseListingDraft(fields: ListingDraftFields): ListingDraftResult {
  const errors = draftErrors(fields)
  if (Object.keys(errors).length > 0) {
    return { ok: false, errors }
  }

  return {
    ok: true,
    value: {
      title: (fields.title ?? '').trim(),
      description: written(fields.description),
      medium: written(fields.medium),
      dimensions: written(fields.dimensions),
      priceCents: parseDollars(fields.price ?? ''),
      quantity: Number(fields.quantity),
    },
  }
}
