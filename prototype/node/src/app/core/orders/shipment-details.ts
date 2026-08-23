/** What a seller types on the mark-shipped form. */
export type ShipmentDetails = {
  carrier: string
  trackingNumber: string
}

export function parseShipmentDetails(input: { carrier?: string | null; trackingNumber?: string | null }): ShipmentDetails {
  return {
    carrier: (input.carrier ?? '').trim(),
    trackingNumber: (input.trackingNumber ?? '').trim(),
  }
}

/** A shipment the customer can follow needs both parts. */
export function isShipmentComplete(details: ShipmentDetails): boolean {
  return details.carrier !== '' && details.trackingNumber !== ''
}
