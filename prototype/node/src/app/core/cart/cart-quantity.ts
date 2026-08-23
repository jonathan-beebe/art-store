/** A cart never holds more of a listing than the seller has left. */
export function quantityWithinStock(input: { requested: number; available: number }): number {
  const { requested, available } = input

  if (requested < 1) {
    throw new RangeError(`a cart holds at least one of a listing, got ${requested}`)
  }
  if (available < 1) {
    throw new RangeError('that listing is sold out')
  }

  return Math.min(requested, available)
}
