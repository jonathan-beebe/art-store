export type CustomerBlock = {
  reason: string
  liftedAt: Date | null
}

export type CustomerStanding = {
  isBlocked: boolean
  reason: string | null
}

export function customerStanding(blocks: readonly CustomerBlock[]): CustomerStanding {
  const active = blocks.find((block) => block.liftedAt === null) ?? null
  return { isBlocked: active !== null, reason: active?.reason ?? null }
}

// A blocked customer can still browse, so this names what a block actually
// removes rather than the block itself.
export function canShop(standing: CustomerStanding): boolean {
  return !standing.isBlocked
}
