import { z } from 'zod'
import type { ListingDraftFields, UploadedImageFormat } from '../../core/listings/listing-draft.ts'

const textPart = z.object({ type: z.literal('field'), value: z.string() }).optional().catch(undefined)

const filePartShape = z.object({
  type: z.literal('file'),
  filename: z.string(),
  mimetype: z.string(),
  toBuffer: z.custom<() => Promise<Buffer>>((value) => typeof value === 'function'),
})

// `toBuffer` reads the part it is a method of, so a file part reaches the route
// as the object `@fastify/multipart` made rather than a copy: the shape is
// checked, the value itself passes through.
const filePart = z
  .custom<z.output<typeof filePartShape>>((value) => filePartShape.safeParse(value).success)
  .optional()
  .catch(undefined)

/**
 * `request.body` under `@fastify/multipart`'s `attachFieldsToBody: true`: one
 * text or file part per submitted field. A part that is not the shape its
 * field expects reads as absent, which `parseListingDraft` then refuses by
 * the same rule it refuses a blank one.
 */
const listingFormBody = z
  .object({
    title: textPart,
    description: textPart,
    medium: textPart,
    dimensions: textPart,
    price: textPart,
    quantity: textPart,
    image: filePart,
  })
  .catch({})

export type ListingFormBody = z.output<typeof listingFormBody>

export type UploadedImagePart = NonNullable<ListingFormBody['image']>

export function parseListingFormBody(body: unknown): ListingFormBody {
  return listingFormBody.parse(body)
}

/** The uploaded image part, or null when the field was left empty — a browser
 * still submits an empty file part for an untouched `<input type="file">`. */
export function uploadedImagePart(body: ListingFormBody): UploadedImagePart | null {
  const part = body.image

  return part !== undefined && part.filename !== '' ? part : null
}

function textValue(part: { value: string } | undefined): string {
  return part?.value ?? ''
}

/** The listing form's text fields, plus the uploaded image's sniffed format
 * (the route reads the bytes and sniffs it — a multipart part's own filename
 * and `Content-Type` decide nothing), in the shape `parseListingDraft` reads. */
export function listingDraftFieldsFrom(
  body: ListingFormBody,
  imageFormat: UploadedImageFormat | null,
): ListingDraftFields {
  return {
    title: textValue(body.title),
    description: textValue(body.description),
    medium: textValue(body.medium),
    dimensions: textValue(body.dimensions),
    price: textValue(body.price),
    quantity: textValue(body.quantity),
    imageFormat,
  }
}
