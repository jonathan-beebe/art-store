export const RECIPIENT_TYPES = ['seller', 'customer', 'admin'] as const

export type RecipientType = (typeof RECIPIENT_TYPES)[number]
