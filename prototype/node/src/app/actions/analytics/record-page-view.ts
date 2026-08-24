import { sql } from 'kysely'
import { newId } from '../../ids.ts'
import type { ActionContext } from '../action-context.ts'
import { pageViewDay } from '../../core/analytics/page-view.ts'
import type { PageViewSite } from '../../core/analytics/page-view-site.ts'

export type PageViewInput = {
  site: PageViewSite
  /** The route's pattern (`/art/:slug`), so every listing page shares one row. */
  pathPattern: string
}

/**
 * Adds one hit to the count for a site, a route pattern, and a day. The unique
 * index on those three columns is what makes the first hit of a day an insert
 * and every later one an increment, in one statement and no read.
 */
export async function recordPageView(
  { db, clock }: Pick<ActionContext, 'db' | 'clock'>,
  { site, pathPattern }: PageViewInput,
): Promise<void> {
  await db
    .insertInto('pageViewCounts')
    .values({ id: newId('pvc', clock.now()), site, pathPattern, day: pageViewDay(clock.now()), count: 1 })
    .onConflict((conflict) =>
      conflict
        .columns(['site', 'pathPattern', 'day'])
        .doUpdateSet({ count: sql<number>`page_view_counts.count + 1` }),
    )
    .execute()
}
