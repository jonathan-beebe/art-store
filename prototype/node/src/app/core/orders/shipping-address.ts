export type ShippingAddress = {
  name: string
  line1: string
  line2: string | null
  city: string
  region: string
  postalCode: string
  country: string
}

export const SHIPPING_ADDRESS_PARTS = ['name', 'line1', 'line2', 'city', 'region', 'postalCode', 'country'] as const
type ShippingAddressPart = (typeof SHIPPING_ADDRESS_PARTS)[number]

const REQUIRED_PARTS: readonly Exclude<ShippingAddressPart, 'line2'>[] = [
  'name',
  'line1',
  'city',
  'region',
  'postalCode',
  'country',
]

/** The required parts (everything but line2) that are blank. Checkout uses it
 * to refuse an incomplete address. */
export function missingAddressParts(address: ShippingAddress): readonly string[] {
  return REQUIRED_PARTS.filter((part) => address[part].trim() === '')
}
