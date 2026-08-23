import { percentOfCents, subtractCents, type Cents } from '../money.ts'

export const PLATFORM_FEE_PERCENT = 10

export function platformFee(subtotalCents: Cents): Cents {
  return percentOfCents(subtotalCents, PLATFORM_FEE_PERCENT)
}

export function sellerNet(subtotalCents: Cents): Cents {
  return subtractCents(subtotalCents, platformFee(subtotalCents))
}
