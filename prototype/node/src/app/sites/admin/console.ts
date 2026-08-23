import type { FastifyPluginCallback } from 'fastify'
import { accountingRoutes } from './routes/accounting.ts'
import { customerRoutes } from './routes/customers.ts'
import { fulfillmentRoutes } from './routes/fulfillments.ts'
import { homeRoutes } from './routes/home.ts'
import { ledgerRoutes } from './routes/ledger.ts'
import { listingRoutes } from './routes/listings.ts'
import { messageRoutes } from './routes/messages.ts'
import { moderationRoutes } from './routes/moderation.ts'
import { orderRoutes } from './routes/orders.ts'
import { payoutRoutes } from './routes/payouts.ts'
import { sellerRoutes } from './routes/sellers.ts'
import { statsRoutes } from './routes/stats.ts'
import { requireAdmin } from '../../plugins/identity.ts'

/**
 * Everything an operator does, behind one guard. The sign-in pages sit outside
 * this plugin: a guard over them would send a signed-out admin in a circle.
 */
export const adminConsoleRoutes: FastifyPluginCallback = (operations, _options, done) => {
  operations.addHook('preHandler', requireAdmin)

  operations.register(homeRoutes)
  operations.register(sellerRoutes)
  operations.register(customerRoutes)
  operations.register(listingRoutes)
  operations.register(orderRoutes)
  operations.register(fulfillmentRoutes)
  operations.register(accountingRoutes)
  operations.register(payoutRoutes)
  operations.register(ledgerRoutes)
  operations.register(statsRoutes)
  operations.register(moderationRoutes)
  operations.register(messageRoutes)

  done()
}
