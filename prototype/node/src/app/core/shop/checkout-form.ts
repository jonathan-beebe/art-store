import { isEmailAddress } from '../auth/email-address.ts'
import { missingAddressParts, type ShippingAddress } from '../orders/shipping-address.ts'

/** What checkout collects before an order can be opened: an address to reach
 * the buyer, and an address to ship to. Every part is filled in — the `ok` arm
 * of `parseCheckoutForm` is the only way to hold one. */
export type CheckoutForm = {
  email: string
  shipping: ShippingAddress
}

export type CheckoutFormFields = {
  email?: string | null
  shipping: Partial<Record<keyof ShippingAddress, string | null | undefined>>
}

/** The submission as it was typed, trimmed — what the page shows again when a
 * part is still missing. */
export type CheckoutEntry = {
  email: string
  shipping: Partial<Record<keyof ShippingAddress, string | null>>
}

/** What checkout shows beside a bad field: the shipping parts by the same
 * key `ShippingAddress` uses, `email` by its own. */
export type CheckoutFormErrors = Partial<Record<'email' | keyof ShippingAddress, string>>

export type CheckoutFormResult =
  | { ok: true; value: CheckoutForm }
  | { ok: false; entered: CheckoutEntry; errors: CheckoutFormErrors }

const EMAIL_ERROR = 'Enter a valid email address.'

/** The sentence a required shipping part shows when it is missing — every
 * part but `line2`, which is never required. */
const REQUIRED_SHIPPING_ERRORS: Record<Exclude<keyof ShippingAddress, 'line2'>, string> = {
  name: 'Enter the full name.',
  line1: 'Enter the address.',
  city: 'Enter the city.',
  region: 'Enter the region.',
  postalCode: 'Enter the postal code.',
  country: 'Enter the country.',
}

function written(value: string | null | undefined): string {
  return (value ?? '').trim()
}

function trimmedForm(fields: CheckoutFormFields): CheckoutForm {
  const { shipping } = fields
  const line2 = written(shipping.line2)

  return {
    email: written(fields.email),
    shipping: {
      name: written(shipping.name),
      line1: written(shipping.line1),
      // The only optional part, so blank means "no second line" rather than "missing".
      line2: line2 === '' ? null : line2,
      city: written(shipping.city),
      region: written(shipping.region),
      postalCode: written(shipping.postalCode),
      country: written(shipping.country),
    },
  }
}

/** Every field checkout refuses, each with the sentence that belongs beside it. */
function checkoutErrors(form: CheckoutForm): CheckoutFormErrors {
  const errors: CheckoutFormErrors = {}
  if (!isEmailAddress(form.email)) errors.email = EMAIL_ERROR

  for (const part of missingAddressParts(form.shipping)) {
    errors[part] = REQUIRED_SHIPPING_ERRORS[part]
  }

  return errors
}

export function parseCheckoutForm(fields: CheckoutFormFields): CheckoutFormResult {
  const trimmed = trimmedForm(fields)
  const errors = checkoutErrors(trimmed)

  return Object.keys(errors).length === 0
    ? { ok: true, value: trimmed }
    : { ok: false, entered: trimmed, errors }
}
