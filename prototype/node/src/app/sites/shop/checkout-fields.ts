import type { ShippingAddress } from '../../core/orders/shipping-address.ts'

export type ShippingField = {
  part: keyof ShippingAddress
  /** The form control's name, which is also the key in the submitted body. */
  name: string
  label: string
  autocomplete: string
  isRequired: boolean
}

/** The address checkout asks for, in the order it asks. The form draws itself
 * from this list and the submitted body is read back through it, so a field
 * cannot exist on one side only. */
export const SHIPPING_FIELDS: readonly ShippingField[] = [
  { part: 'name', name: 'shipping_name', label: 'Full name', autocomplete: 'name', isRequired: true },
  { part: 'line1', name: 'shipping_line1', label: 'Address', autocomplete: 'address-line1', isRequired: true },
  { part: 'line2', name: 'shipping_line2', label: 'Address line 2', autocomplete: 'address-line2', isRequired: false },
  { part: 'city', name: 'shipping_city', label: 'City', autocomplete: 'address-level2', isRequired: true },
  { part: 'region', name: 'shipping_region', label: 'Region', autocomplete: 'address-level1', isRequired: true },
  { part: 'postalCode', name: 'shipping_postal_code', label: 'Postal code', autocomplete: 'postal-code', isRequired: true },
  { part: 'country', name: 'shipping_country', label: 'Country', autocomplete: 'country-name', isRequired: true },
]

export type SubmittedForm = Record<string, string | undefined>

export function shippingFromForm(
  submitted: SubmittedForm,
): Partial<Record<keyof ShippingAddress, string>> {
  return Object.fromEntries(SHIPPING_FIELDS.map((field) => [field.part, submitted[field.name]]))
}

/** What the email field is called when checkout's own worded list of missing
 * parts names it — the one missing part with no `ShippingField` behind it. */
const EMAIL_FIELD_LABEL = 'Email address'

/** `parseCheckoutForm`'s missing parts, worded the way the page shows them
 * back: each shipping part by its field label, `email` by its own. */
export function missingFieldLabels(missingParts: readonly string[]): readonly string[] {
  return missingParts.map((part) => {
    if (part === 'email') return EMAIL_FIELD_LABEL

    const field = SHIPPING_FIELDS.find((candidate) => candidate.part === part)

    return field ? field.label : part
  })
}
