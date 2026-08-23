export const LEDGER_ENTRY_TYPES = ['held', 'released', 'paid_out'] as const

export type LedgerEntryType = (typeof LEDGER_ENTRY_TYPES)[number]
