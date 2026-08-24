import { createHash } from 'node:crypto'

export function normalizeEmail(email: string | null | undefined): string {
  return (email ?? '').trim().toLowerCase()
}

const EMAIL_ADDRESS_PATTERN = /^[^@\s]+@[^@\s]+\.[^@\s]+$/

export function isEmailAddress(email: string | null | undefined): boolean {
  return EMAIL_ADDRESS_PATTERN.test(normalizeEmail(email))
}

/**
 * An address, as a log line is allowed to carry it: `docs/alignment.md` §2.1
 * forbids the raw address, so this is a short digest instead — the same
 * shape `redactedRateLimitKey` gives a rate-limit key, mirrored here rather
 * than shared, since what each redacts means something different to whoever
 * reads the line. A digest still lets a log reader see the same address
 * asked twice without learning who it belongs to.
 */
export function redactedEmail(email: string): string {
  return createHash('sha256').update(normalizeEmail(email)).digest('hex').slice(0, 16)
}
