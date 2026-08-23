import { isEmailAddress } from '../auth/email-address.ts'
import { missingAddressParts, type ShippingAddress } from '../orders/shipping-address.ts'

/** What checkout collects before an order can be opened: an address to reach
 * the buyer, and an address to ship to. */
export type CheckoutForm = {
  email: string
  shipping: ShippingAddress
}

export type CheckoutFormFields = {
  email?: string | null
  shipping: Partial<Record<keyof ShippingAddress, string | null | undefined>>
}

function written(value: string | null | undefined): string {
  return (value ?? '').trim()
}

export function parseCheckoutForm(fields: CheckoutFormFields): CheckoutForm {
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
export function missingCheckoutParts(form: CheckoutForm): readonly string[] {
  const email = isEmailAddress(form.email) ? [] : ['email']

  return [...email, ...missingAddressParts(form.shipping)]
}

export function isCheckoutComplete(form: CheckoutForm): boolean {
  return missingCheckoutParts(form).length === 0
}
