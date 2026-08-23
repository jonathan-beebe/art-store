/** What a seller types on the mark-shipped form. Both parts are written — the
 * `ok` arm of `parseShipmentDetails` is the only way to hold one, so a
 * shipment the customer cannot follow never reaches the fulfillment. */
export type ShipmentDetails = {
  carrier: string
  trackingNumber: string
}

export type ShipmentDetailsFields = { carrier?: string | null; trackingNumber?: string | null }

export type ShipmentDetailsErrors = Partial<Record<'carrier' | 'trackingNumber', string>>

export type ShipmentDetailsResult =
  | { ok: true; value: ShipmentDetails }
  | { ok: false; errors: ShipmentDetailsErrors }

export function parseShipmentDetails(fields: ShipmentDetailsFields): ShipmentDetailsResult {
  const carrier = (fields.carrier ?? '').trim()
  const trackingNumber = (fields.trackingNumber ?? '').trim()

  const errors: ShipmentDetailsErrors = {}
  if (carrier === '') errors.carrier = 'Enter the carrier.'
  if (trackingNumber === '') errors.trackingNumber = 'Enter the tracking number.'

  if (Object.keys(errors).length > 0) {
    return { ok: false, errors }
  }

  return { ok: true, value: { carrier, trackingNumber } }
}
