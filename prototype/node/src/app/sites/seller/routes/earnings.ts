import { z } from 'zod'
import { sellerBalance } from '../../../actions/escrow/ledger-balances.ts'
import { formatCents } from '../../../core/money.ts'
import { listPage } from '../../../core/paging/list-page.ts'
import { statusLabel } from '../../../core/status-label.ts'
import type { ZodRoutes } from '../../../http/zod-type-provider.ts'
import { currentSellerId } from '../current-seller.ts'
import { formatDate } from '../format.ts'
import { fulfillmentCountsByStatus, fulfillmentsForSeller, itemTitlesByOrder } from '../queries/fulfillments.ts'
import { countLedgerEntriesForSeller, ledgerEntriesForSeller, payoutsForSeller } from '../queries/payouts.ts'

// Deep enough that most sellers never see a second page of either table.
const SALES_PER_PAGE = 25
const MOVEMENTS_PER_PAGE = 25

const showQuery = z.object({
  sales_page: z.string().optional(),
  movements_page: z.string().optional(),
})

export const earningsRoutes: ZodRoutes = (portal, _options, done) => {
  portal.get('/earnings', { schema: { querystring: showQuery } }, async (request, reply) => {
    const { db } = request.server
    const sellerId = currentSellerId(request)
    const asked = request.query

    const counts = await fulfillmentCountsByStatus(db, sellerId)
    const salesTotal = [...counts.values()].reduce((sum, count) => sum + count, 0)
    const salesPage = listPage({ requested: asked.sales_page, size: SALES_PER_PAGE, totalCount: salesTotal })
    const fulfillments = await fulfillmentsForSeller(db, sellerId, salesPage)

    const movementsTotal = await countLedgerEntriesForSeller(db, sellerId)
    const movementsPage = listPage({
      requested: asked.movements_page,
      size: MOVEMENTS_PER_PAGE,
      totalCount: movementsTotal,
    })
    const movements = await ledgerEntriesForSeller(db, sellerId, movementsPage)

    const itemTitles = await itemTitlesByOrder(
      db,
      fulfillments.map((fulfillment) => fulfillment.orderId),
      sellerId,
    )
    const balance = await sellerBalance({ db }, sellerId)
    const payouts = await payoutsForSeller(db, sellerId)

    return reply.render('earnings/show', {
      title: 'Earnings',
      fulfillments,
      itemTitles,
      balance,
      payouts,
      movements,
      salesPage,
      movementsPage,
      salesQuery: `movements_page=${movementsPage.number}`,
      movementsQuery: `sales_page=${salesPage.number}`,
      statusLabel,
      formatCents,
      formatDate,
    })
  })

  done()
}
