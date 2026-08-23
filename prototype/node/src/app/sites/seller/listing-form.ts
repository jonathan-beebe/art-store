import type { ListingDraftFields, UploadedImageFormat } from '../../core/listings/listing-draft.ts'

export type MultipartField = { type: 'field'; value: unknown }
export type MultipartFilePart = {
  type: 'file'
  filename: string
  mimetype: string
  toBuffer(): Promise<Buffer>
}

/** The shape `@fastify/multipart`'s `attachFieldsToBody: true` puts on
 * `request.body`: one text or file part per submitted field. */
export type MultipartBody = Record<string, MultipartField | MultipartFilePart | undefined>

const TEXT_FIELDS = ['title', 'description', 'medium', 'dimensions', 'price', 'quantity'] as const

function textValue(body: MultipartBody, name: string): string {
  const part = body[name]
  return part !== undefined && part.type === 'field' && typeof part.value === 'string' ? part.value : ''
}

/** The uploaded image part, or null when the field was left empty — a browser
 * still submits an empty file part for an untouched `<input type="file">`. */
export function uploadedImagePart(body: MultipartBody): MultipartFilePart | null {
  const part = body.image
  return part !== undefined && part.type === 'file' && part.filename !== '' ? part : null
}

/** The listing form's text fields, plus the uploaded image's sniffed format
 * (the route reads the bytes and sniffs it — a multipart part's own filename
 * and `Content-Type` decide nothing), in the shape `listingDraftErrors` and
 * `parseListingDraft` read. */
export function listingDraftFieldsFrom(
  body: MultipartBody,
  imageFormat: UploadedImageFormat | null,
): ListingDraftFields {
  const fields: Record<string, string> = {}
  for (const name of TEXT_FIELDS) fields[name] = textValue(body, name)

  return { ...fields, imageFormat }
}
