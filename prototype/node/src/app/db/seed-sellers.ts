import { claimSellerIdentity } from '../actions/auth/claim-seller-identity.ts'
import { fixedClock } from '../clock.ts'
import type { SellerId } from '../core/ids/entity-ids.ts'
import type { AppDatabase } from './database.ts'

const VERIFIED_AT = new Date('2026-06-01T00:00:00.000Z')

export type SeededSeller = { email: string; name: string; shopName: string }

/** Four verified sellers a reviewer can sign in as through the debug magic link. */
export const SEEDED_SELLERS: readonly SeededSeller[] = [
  { email: 'maya@example.com', name: 'Maya Reyes', shopName: 'Terra & Glaze Ceramics' },
  { email: 'noah@example.com', name: 'Noah Chen', shopName: 'North Light Editions' },
  { email: 'priya@example.com', name: 'Priya Anand', shopName: 'Priya Anand Textile Studio' },
  { email: 'leo@example.com', name: 'Leo Martins', shopName: 'Leo Martins Photography' },
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
