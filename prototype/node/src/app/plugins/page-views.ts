import type { FastifyReply } from 'fastify'
import { recordPageView } from '../actions/analytics/record-page-view.ts'
import { isCountablePageView } from '../core/analytics/page-view.ts'
import { pageViewSite } from '../core/analytics/page-view-site.ts'
import { rootPlugin } from './root-plugin.ts'

/**
 * Counts page views rather than logging them: one row per site, route pattern,
 * and day, so the admin site reads traffic without a table that grows per hit.
 *
 * Registered once at the root because a hook added there runs for every site,
 * and the site a hit belongs to is read back off the route's own pattern.
 */
export const pageViewRollup = rootPlugin({ name: 'pageViewRollup' }, (app) => {
  app.addHook('onResponse', async (request, reply) => {
    // A request that matched no route has no pattern to count against.
    const pathPattern = request.routeOptions.url
    if (pathPattern === undefined) return

    const countable = isCountablePageView({
      method: request.method,
      statusCode: reply.statusCode,
      contentType: responseContentType(reply),
    })
    if (!countable) return

    await recordPageView(
      { db: app.db, clock: app.clock },
      { site: pageViewSite(pathPattern), pathPattern },
    )
  })
})

function responseContentType(reply: FastifyReply): string | null {
  const header = reply.getHeader('content-type')

  return typeof header === 'string' ? header : null
}
