import { z } from 'zod'
import { markNotificationRead } from '../../../actions/notifications/mark-notification-read.ts'
import { listPage } from '../../../core/paging/list-page.ts'
import { idParams } from '../../../http/request-schema.ts'
import type { ZodRoutes } from '../../../http/zod-type-provider.ts'
import { currentSellerId } from '../current-seller.ts'
import { formatDateTime } from '../format.ts'
import { sellerNotFound } from '../not-found.ts'
import { countNotificationsForSeller, notificationsForSeller, ownedNotification } from '../queries/notifications.ts'

// Deep enough that most sellers never see a second page.
const NOTIFICATIONS_PER_PAGE = 25

const indexQuery = z.object({ page: z.string().optional() })

export const notificationsRoutes: ZodRoutes = (portal, _options, done) => {
  portal.get('/notifications', { schema: { querystring: indexQuery } }, async (request, reply) => {
    const { db } = request.server
    const sellerId = currentSellerId(request)
    const page = listPage({
      requested: request.query.page,
      size: NOTIFICATIONS_PER_PAGE,
      totalCount: await countNotificationsForSeller(db, sellerId),
    })
    const notifications = await notificationsForSeller(db, sellerId, page)

    return reply.render('notifications/index', {
      title: 'Notifications',
      notifications,
      page,
      formatDateTime,
    })
  })

  portal.post(
    '/notifications/:id/read',
    { schema: { params: idParams('ntf') } },
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
