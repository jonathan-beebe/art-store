import type { FastifyPluginCallback, FastifyReply, FastifyRequest } from 'fastify'
import { sellerBalance } from '../../../actions/escrow/seller-balance.ts'
import { formatCents } from '../../../core/money.ts'
import { listingStatusTally } from '../../../core/reports/listing-status-tally.ts'
import { statusLabel } from '../../../core/status-label.ts'
import { currentSellerId } from '../current-seller.ts'
import { formatDateTime } from '../format.ts'
import { awaitingShipmentCount } from '../queries/fulfillments.ts'
import { listingStatusCounts } from '../queries/listings.ts'
import { recentNotificationsForSeller, unreadNotificationCount } from '../queries/notifications.ts'

const RECENT_NOTIFICATIONS = 5

async function show(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  const { db } = request.server
  const sellerId = currentSellerId(request)

  const statusCounts = await listingStatusCounts(db, sellerId)
  const awaitingShipment = await awaitingShipmentCount(db, sellerId)
  const balance = await sellerBalance({ db }, sellerId)
  const unreadCount = await unreadNotificationCount(db, sellerId)
  const recentNotifications = await recentNotificationsForSeller(db, sellerId, RECENT_NOTIFICATIONS)

  return reply.render('home', {
    title: 'Dashboard',
    tally: listingStatusTally(statusCounts),
    awaitingShipment,
    balance,
    unreadCount,
    recentNotifications,
    statusLabel,
    formatCents,
    formatDateTime,
  })
}

export const homeRoutes: FastifyPluginCallback = (portal, _options, done) => {
  portal.get('/', show)

  done()
}
