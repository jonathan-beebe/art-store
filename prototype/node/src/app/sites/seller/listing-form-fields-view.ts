import type { ListingDraftErrors, ListingDraftFields } from '../../core/listings/listing-draft.ts'

/** A text control's render data: what it shows, and the error id it points
 * `aria-describedby` at when it has one. */
export type ListingFormFieldView = { value: string; errorId: string | null }

/** The image control has no text value of its own to show back. */
export type ListingFormImageView = { errorId: string | null }

export type ListingFormFieldsView = {
  title: ListingFormFieldView
  description: ListingFormFieldView
  medium: ListingFormFieldView
  dimensions: ListingFormFieldView
  price: ListingFormFieldView
  quantity: ListingFormFieldView
  image: ListingFormImageView
}

/** The id `field-error.ejs` gives its `<p>` for this field — `null` when the
 * field carries no error, so a control's `aria-describedby` has nothing to
 * point at and its `aria-invalid` stays unset. */
function errorId(fieldId: string, message: string | undefined): string | null {
  return message === undefined ? null : `${fieldId}-error`
}

/**
 * The seven listing-form controls, each carrying the value it shows and the
 * error id it needs for `aria-describedby`/`aria-invalid` — one shape for
 * every control, so the form template does no lookups of its own.
 */
export function listingFormFieldsView(
  fields: ListingDraftFields,
  errors: ListingDraftErrors,
): ListingFormFieldsView {
  return {
    title: { value: fields.title ?? '', errorId: errorId('listing_title', errors.title) },
    description: { value: fields.description ?? '', errorId: errorId('listing_description', errors.description) },
    medium: { value: fields.medium ?? '', errorId: errorId('listing_medium', errors.medium) },
    dimensions: { value: fields.dimensions ?? '', errorId: errorId('listing_dimensions', errors.dimensions) },
    price: { value: fields.price ?? '', errorId: errorId('listing_price', errors.price) },
    quantity: { value: fields.quantity ?? '', errorId: errorId('listing_quantity', errors.quantity) },
    image: { errorId: errorId('listing_image', errors.image) },
  }
}
