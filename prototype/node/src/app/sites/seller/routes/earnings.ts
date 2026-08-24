import type { FastifyPluginCallback, FastifyReply, FastifyRequest } from 'fastify'
import { sellerBalance } from '../../../actions/escrow/seller-balance.ts'
import { formatCents } from '../../../core/money.ts'
import { statusLabel } from '../../../core/status-label.ts'
import { currentSellerId } from '../current-seller.ts'
import { formatDate } from '../format.ts'
import { fulfillmentsForSeller, itemTitlesByOrder } from '../queries/fulfillments.ts'
import { ledgerEntriesForSeller, payoutsForSeller } from '../queries/payouts.ts'

async function show(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  const { db } = request.server
  const sellerId = currentSellerId(request)
  const fulfillments = await fulfillmentsForSeller(db, sellerId)

  const itemTitles = await itemTitlesByOrder(
    db,
    fulfillments.map((fulfillment) => fulfillment.orderId),
    sellerId,
  )
  const balance = await sellerBalance({ db }, sellerId)
  const payouts = await payoutsForSeller(db, sellerId)
  const movements = await ledgerEntriesForSeller(db, sellerId)

  return reply.render('earnings/show', {
    title: 'Earnings',
    fulfillments,
    itemTitles,
    balance,
    payouts,
    movements,
    statusLabel,
    formatCents,
    formatDate,
  })
}

export const earningsRoutes: FastifyPluginCallback = (portal, _options, done) => {
  portal.get('/earnings', show)

  done()
}
