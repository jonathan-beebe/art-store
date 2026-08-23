export const MAGIC_LINK_LIFETIME_MINUTES = 15

export type MagicLinkStatus = 'usable' | 'expired' | 'consumed'

export function magicLinkStatus(
  link: { expiresAt: Date; consumedAt: Date | null },
  now: Date,
): MagicLinkStatus {
  if (link.consumedAt !== null) {
    return 'consumed'
  }
  return now.getTime() >= link.expiresAt.getTime() ? 'expired' : 'usable'
}

export function magicLinkExpiresAt(issuedAt: Date): Date {
  return new Date(issuedAt.getTime() + MAGIC_LINK_LIFETIME_MINUTES * 60 * 1000)
}
