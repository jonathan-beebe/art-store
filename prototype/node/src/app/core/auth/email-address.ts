export function normalizeEmail(email: string | null | undefined): string {
  return (email ?? '').trim().toLowerCase()
}

const EMAIL_ADDRESS_PATTERN = /^[^@\s]+@[^@\s]+\.[^@\s]+$/

export function isEmailAddress(email: string | null | undefined): boolean {
  return EMAIL_ADDRESS_PATTERN.test(normalizeEmail(email))
}
