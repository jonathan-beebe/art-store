export const REMOVAL_KINDS = ['temporary', 'permanent'] as const
export type RemovalKind = (typeof REMOVAL_KINDS)[number]

export type ListingRemoval = {
  kind: RemovalKind
  reason: string
  liftedAt: Date | null
}

/** Generic in the row so a caller keeps whatever else it read alongside the removal. */
export function activeRemoval<Removal extends ListingRemoval>(
  removals: readonly Removal[],
): Removal | null {
  return removals.find((removal) => removal.liftedAt === null) ?? null
}

export function canLiftRemoval(kind: RemovalKind): boolean {
  return kind === 'temporary'
}
