import type { FastifyPluginCallback } from 'fastify'
import { adminPage } from '../page.ts'
import { listingEventTallies } from '../queries/listing-event-tallies.ts'
import { pageViewsByDay, pageViewsByPattern } from '../queries/page-view-report.ts'

export const statsRoutes: FastifyPluginCallback = (admin, _options, done) => {
  admin.get('/stats', async (_request, reply) => {
    const context = { db: admin.db }

    return reply.render(
      'stats',
      adminPage('Site stats', {
        days: await pageViewsByDay(context),
        patterns: await pageViewsByPattern(context),
        events: await listingEventTallies(context),
      }),
    )
  })

  done()
}
