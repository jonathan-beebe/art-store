export type CustomerBlock = {
  reason: string
  liftedAt: Date | null
}

export type CustomerStanding = {
  isBlocked: boolean
  reason: string | null
}

/** Generic in the row so a caller keeps whatever else it read alongside the block. */
export function activeBlock<Block extends CustomerBlock>(
  blocks: readonly Block[],
): Block | null {
  return blocks.find((block) => block.liftedAt === null) ?? null
}

export function customerStanding(blocks: readonly CustomerBlock[]): CustomerStanding {
  const active = activeBlock(blocks)
  return { isBlocked: active !== null, reason: active?.reason ?? null }
}

// A blocked customer can still browse, so this names what a block actually
// removes rather than the block itself.
export function canShop(standing: CustomerStanding): boolean {
  return !standing.isBlocked
}
