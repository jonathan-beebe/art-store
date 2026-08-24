import type { Cents } from '../money.ts'
import { multiplyCents } from '../money.ts'
import type { SellerId } from '../ids/entity-ids.ts'

export type CartLine = {
  sellerId: SellerId
  unitPriceCents: Cents
  quantity: number
}

export function createCartLine(line: CartLine): CartLine {
  if (line.quantity < 1) {
    throw new RangeError(`a cart line covers at least one item, got ${line.quantity}`)
  }
  return line
}

export function cartLineTotal(line: CartLine): Cents {
  return multiplyCents(line.unitPriceCents, line.quantity)
}
