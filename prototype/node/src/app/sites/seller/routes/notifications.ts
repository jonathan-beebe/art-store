import { markNotificationRead } from '../../../actions/notifications/mark-notification-read.ts'
import { idParams } from '../../../http/request-schema.ts'
import type { ZodRoutes } from '../../../http/zod-type-provider.ts'
import { currentSellerId } from '../current-seller.ts'
import { formatDateTime } from '../format.ts'
import { sellerNotFound } from '../not-found.ts'
import { notificationsForSeller, ownedNotification } from '../queries/notifications.ts'

export const notificationsRoutes: ZodRoutes = (portal, _options, done) => {
  portal.get('/notifications', async (request, reply) => {
    const notifications = await notificationsForSeller(request.server.db, currentSellerId(request))

    return reply.render('notifications/index', {
      title: 'Notifications',
      notifications,
      formatDateTime,
    })
  })

  portal.post(
    '/notifications/:id/read',
    { schema: { params: idParams } },
    async (request, reply) => {
      const notificationId = request.params.id
      const { db, clock } = request.server
      const owned = await ownedNotification(db, currentSellerId(request), notificationId)
      if (owned === null) return sellerNotFound(reply)

      await markNotificationRead({ db, clock }, notificationId)

      reply.setFlash({ notice: 'Marked as read.' })

      return reply.redirect('/seller/notifications')
    },
  )

  done()
}
