import type { FastifyPluginCallback } from 'fastify'
import { z } from 'zod'
import { markNotificationRead } from '../../../actions/notifications/mark-notification-read.ts'
import { requireVerifiedCustomer } from '../../../plugins/identity.ts'
import { findCustomerNotification } from '../queries/find-customer-notifications.ts'
import { renderNotFound } from '../shop-page.ts'
import { storefrontCustomer } from '../storefront-customer.ts'

const parameters = z.object({ id: z.coerce.number().int().positive() })

export const notificationRoutes: FastifyPluginCallback = (shop, _options, done) => {
  shop.post(
    '/account/notifications/:id/read',
    { preHandler: requireVerifiedCustomer },
    async (request, reply) => {
      const { db, clock } = shop
      const { id } = parameters.parse(request.params)
      const customer = storefrontCustomer(request)

      const owned = await findCustomerNotification(db, { id, customerId: customer.id })
      if (owned === null) return renderNotFound(reply)

      await markNotificationRead({ db, clock }, id)

      return await reply.redirect('/account')
    },
  )

  done()
}
