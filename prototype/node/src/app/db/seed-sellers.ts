import { claimSellerIdentity } from '../actions/auth/claim-seller-identity.ts'
import { fixedClock } from '../clock.ts'
import type { SellerId } from '../core/ids/entity-ids.ts'
import type { AppDatabase } from './database.ts'

const VERIFIED_AT = new Date('2026-06-01T00:00:00.000Z')

export type SeededSeller = { email: string; name: string; shopName: string }

/** Four verified sellers a reviewer can sign in as through the debug magic link. */
export const SEEDED_SELLERS: readonly SeededSeller[] = [
  { email: 'molly@example.com', name: 'Molly Weasley', shopName: 'The Burrow Craftworks' },
  { email: 'dean@example.com', name: 'Dean Thomas', shopName: 'Dean Thomas Studio' },
  { email: 'sybill@example.com', name: 'Sybill Trelawney', shopName: "Trelawney's Tower Studio" },
  { email: 'colin@example.com', name: 'Colin Creevey', shopName: 'Creevey Camera Works' },
]

/** Claims and profiles every seeded seller, keyed by email for the catalog to join against. */
export async function seedSellers(db: AppDatabase): Promise<Record<string, SellerId>> {
  const clock = fixedClock(VERIFIED_AT)
  const sellerIdsByEmail: Record<string, SellerId> = {}

  for (const seller of SEEDED_SELLERS) {
    const claimed = await claimSellerIdentity({ db, clock }, seller.email)

    await db
      .updateTable('sellers')
      .set({ name: seller.name, shopName: seller.shopName })
      .where('id', '=', claimed.id)
      .execute()

    sellerIdsByEmail[seller.email] = claimed.id
  }

  return sellerIdsByEmail
}
