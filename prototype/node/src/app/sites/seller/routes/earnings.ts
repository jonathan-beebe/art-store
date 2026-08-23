import type { FastifyPluginCallback, FastifyReply, FastifyRequest } from 'fastify'
import { sellerBalance } from '../../../actions/escrow/seller-balance.ts'
import { formatCents } from '../../../core/money.ts'
import { statusLabel } from '../../../core/status-label.ts'
import { currentSellerId } from '../current-seller.ts'
import { formatDate } from '../format.ts'
import { fulfillmentsForSeller, itemTitlesByOrder } from '../queries/fulfillments.ts'
import { payoutsForSeller } from '../queries/payouts.ts'

async function show(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  const { db } = request.server
  const sellerId = currentSellerId(request)
  const fulfillments = await fulfillmentsForSeller(db, sellerId)

  const [itemTitles, balance, payouts] = await Promise.all([
    itemTitlesByOrder(db, fulfillments.map((fulfillment) => fulfillment.orderId), sellerId),
    sellerBalance({ db }, sellerId),
    payoutsForSeller(db, sellerId),
  ])

  return reply.render('earnings/show', {
    title: 'Earnings',
    fulfillments,
    itemTitles,
    balance,
    payouts,
    statusLabel,
    formatCents,
    formatDate,
  })
}

export const earningsRoutes: FastifyPluginCallback = (portal, _options, done) => {
  portal.get('/earnings', show)

  done()
}
