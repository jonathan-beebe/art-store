import { createHash } from 'node:crypto'

/** Only the digest is ever stored, so a leaked row cannot be replayed as a link. */
export function digestMagicLinkToken(token: string): string {
  return createHash('sha256').update(token).digest('hex')
}
