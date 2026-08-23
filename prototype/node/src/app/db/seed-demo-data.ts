import { findAdminByEmail } from '../actions/auth/find-admin-by-email.ts'
import type { Clock } from '../clock.ts'
import { SEEDED_ADMINS } from './seed-admins.ts'
import { seedCatalog } from './seed-catalog.ts'
import { seedCustomers } from './seed-customers.ts'
import { seedOrderHistory } from './seed-order-history.ts'
import { seedPageViews } from './seed-page-views.ts'
import { seedSellers } from './seed-sellers.ts'
import type { AppDatabase } from './database.ts'

export type SeedDemoDataSummary = {
  sellerCount: number
  listingCount: number
  customerCount: number
  orderCount: number
  pageViewRowCount: number
}

/**
 * The sellers, catalog, customers, order history, and traffic a reviewer
 * needs to demo every page. Refuses to run twice: a database that already
 * holds a seller is left untouched, so a second `npm run seed` cannot double
 * the catalog or replay the ledger. Every date in the catalog and order
 * history is fixed regardless of when this runs, so the demo reads the same
 * on any day; only the page-view history is dated relative to `clock`, so it
 * always covers the 14 days up to today.
 */
export async function seedDemoData({
  db,
  clock,
}: {
  db: AppDatabase
  clock: Clock
}): Promise<SeedDemoDataSummary | null> {
  const alreadySeeded = await db.selectFrom('sellers').select('id').limit(1).executeTakeFirst()
  if (alreadySeeded !== undefined) return null

  const admin = await findAdminByEmail({ db }, SEEDED_ADMINS[0]?.email ?? '')
  if (admin === null) {
    throw new Error('seedDemoData: seed the admins before the demo data')
  }

  const sellerIdsByEmail = await seedSellers(db)
  const { listingIdsByTitle } = await seedCatalog(db, sellerIdsByEmail, admin.id)
  const customers = await seedCustomers(db, listingIdsByTitle, admin.id)
  const orderHistory = await seedOrderHistory(db, customers.casey, listingIdsByTitle)
  const pageViewRowCount = await seedPageViews(db, clock.now())

  return {
    sellerCount: Object.keys(sellerIdsByEmail).length,
    listingCount: Object.keys(listingIdsByTitle).length,
    customerCount: customers.count,
    orderCount: [orderHistory.paidOrder, orderHistory.shippedOrder, orderHistory.deliveredOrder].length,
    pageViewRowCount,
  }
}
