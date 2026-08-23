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

export type CheckoutFormResult =
  | { ok: true; value: CheckoutForm }
  | { ok: false; entered: CheckoutEntry; errors: readonly string[] }

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

/** The parts checkout still needs, in the order the form asks for them. */
function missingCheckoutParts(form: CheckoutForm): readonly string[] {
  const email = isEmailAddress(form.email) ? [] : ['email']

  return [...email, ...missingAddressParts(form.shipping)]
}

export function parseCheckoutForm(fields: CheckoutFormFields): CheckoutFormResult {
  const trimmed = trimmedForm(fields)
  const missing = missingCheckoutParts(trimmed)

  return missing.length === 0
    ? { ok: true, value: trimmed }
    : { ok: false, entered: trimmed, errors: missing }
}
