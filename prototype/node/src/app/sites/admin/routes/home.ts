import type { FastifyPluginCallback } from 'fastify'
import { adminPage } from '../page.ts'
import { pageViewTotals } from '../queries/page-view-report.ts'
import { platformMoney } from '../queries/platform-money.ts'
import { platformTallies } from '../queries/platform-tallies.ts'
import { pageViewDay } from '../../../core/analytics/page-view.ts'

export const homeRoutes: FastifyPluginCallback = (admin, _options, done) => {
  admin.get('/', async (_request, reply) => {
    const context = { db: admin.db }

    return reply.render(
      'home',
      adminPage('Overview', {
        tallies: await platformTallies(context),
        money: await platformMoney(context),
        traffic: await pageViewTotals(context, pageViewDay(admin.clock.now())),
      }),
    )
  })

  done()
}
