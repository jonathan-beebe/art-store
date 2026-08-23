import type { FastifyPluginCallback, FastifyReply, FastifyRequest } from 'fastify'
import { markNotificationRead } from '../../../actions/notifications/mark-notification-read.ts'
import { currentSellerId } from '../current-seller.ts'
import { formatDateTime } from '../format.ts'
import { sellerNotFound } from '../not-found.ts'
import { parseIdParam } from '../../../http/id-param.ts'
import { notificationsForSeller, ownedNotification } from '../queries/notifications.ts'

async function index(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  const notifications = await notificationsForSeller(request.server.db, currentSellerId(request))

  return reply.render('notifications/index', { title: 'Notifications', notifications, formatDateTime })
}

async function markRead(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  const id = parseIdParam(request.params)
  if (id === null) return sellerNotFound(reply)

  const { db, clock } = request.server
  const owned = await ownedNotification(db, currentSellerId(request), id)
  if (owned === null) return sellerNotFound(reply)

  await markNotificationRead({ db, clock }, id)

  reply.setFlash({ notice: 'Marked as read.' })
  return reply.redirect('/seller/notifications')
}

export const notificationsRoutes: FastifyPluginCallback = (portal, _options, done) => {
  portal.get('/notifications', index)
  portal.post('/notifications/:id/read', markRead)

  done()
}
