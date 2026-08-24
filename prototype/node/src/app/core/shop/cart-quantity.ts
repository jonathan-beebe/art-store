const WHOLE_NUMBER_PATTERN = /^\d+$/

export type CartQuantityErrors = Partial<Record<'quantity', string>>

export type CartQuantityResult = { ok: true; value: number } | { ok: false; errors: CartQuantityErrors }

/**
 * How many of a listing to add to the cart, as the visitor typed it. A field
 * left blank means one; anything else has to be a whole number within what
 * remains in stock, or it is refused by name so the form that asked for it
 * can put the reason beside the field.
 */
export function parseCartQuantity(raw: string | undefined, available: number): CartQuantityResult {
  const trimmed = (raw ?? '').trim()
  if (trimmed === '') return { ok: true, value: 1 }

  const error = { quantity: `Choose a quantity from 1 to ${available}.` }
  if (!WHOLE_NUMBER_PATTERN.test(trimmed)) return { ok: false, errors: error }

  const quantity = Number(trimmed)
  if (quantity < 1 || quantity > available) return { ok: false, errors: error }

  return { ok: true, value: quantity }
}
