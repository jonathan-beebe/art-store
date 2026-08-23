import type { FastifyPluginCallback } from 'fastify'
import { adminPage } from '../page.ts'
import { sellerAccounts } from '../queries/seller-accounts.ts'
import { platformMoney } from '../queries/platform-money.ts'

export const accountingRoutes: FastifyPluginCallback = (admin, _options, done) => {
  admin.get('/accounting', async (_request, reply) => {
    const context = { db: admin.db }

    return reply.render(
      'accounting',
      adminPage('Accounting', {
        accounts: await sellerAccounts(context),
        totals: await platformMoney(context),
      }),
    )
  })

  done()
}
