import { markNotificationRead } from '../../../actions/notifications/mark-notification-read.ts'
import { idParams } from '../../../http/request-schema.ts'
import type { ZodRoutes } from '../../../http/zod-type-provider.ts'
import { requireVerifiedCustomer } from '../../../plugins/identity.ts'
import { findCustomerNotification } from '../queries/find-customer-notifications.ts'
import { renderNotFound } from '../shop-page.ts'
import { storefrontCustomer } from '../storefront-customer.ts'

export const notificationRoutes: ZodRoutes = (shop, _options, done) => {
  shop.post(
    '/account/notifications/:id/read',
    { schema: { params: idParams('ntf') }, preHandler: requireVerifiedCustomer },
    async (request, reply) => {
      const { db, clock } = shop
      const id = request.params.id
      const customer = storefrontCustomer(request)

      const owned = await findCustomerNotification(db, { id, customerId: customer.id })
      if (owned === null) return renderNotFound(reply)

      await markNotificationRead({ db, clock }, id)

      return await reply.redirect('/account')
    },
  )

  done()
}
