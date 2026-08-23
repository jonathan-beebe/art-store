import type { ActionContext } from '../../../actions/action-context.ts'
import { pageViewWeek } from '../../../core/analytics/page-view-window.ts'

/** Traffic on one day, across every site. */
export type DayCount = { day: string; count: number }

/** Traffic on one route pattern of one site, across every day. */
export type PatternCount = { site: string; pathPattern: string; count: number }

export type PageViewTotals = { todayCount: number; weekCount: number }

/** Today's traffic and the seven-day window it sits at the end of. */
export async function pageViewTotals(
  context: Pick<ActionContext, 'db'>,
  today: string,
): Promise<PageViewTotals> {
  const week = pageViewWeek(today)

  return {
    todayCount: await countBetween(context, today, today),
    weekCount: await countBetween(context, week.firstDay, week.lastDay),
  }
}

/** Every day that saw traffic, newest first. */
export async function pageViewsByDay({
  db,
}: Pick<ActionContext, 'db'>): Promise<readonly DayCount[]> {
  const rows = await db
    .selectFrom('pageViewCounts')
    .select(['day'])
    .select((eb) => eb.fn.sum<number>('count').as('count'))
    .groupBy('day')
    .orderBy('day', 'desc')
    .execute()

  return rows.map((row) => ({ day: row.day, count: Number(row.count) }))
}

/** Every route pattern that saw traffic, busiest first. */
export async function pageViewsByPattern({
  db,
}: Pick<ActionContext, 'db'>): Promise<readonly PatternCount[]> {
  const rows = await db
    .selectFrom('pageViewCounts')
    .select(['site', 'pathPattern'])
    .select((eb) => eb.fn.sum<number>('count').as('count'))
    .groupBy(['site', 'pathPattern'])
    .orderBy('count', 'desc')
    .orderBy('site')
    .orderBy('pathPattern')
    .execute()

  return rows.map((row) => ({
    site: row.site,
    pathPattern: row.pathPattern,
    count: Number(row.count),
  }))
}

async function countBetween(
  { db }: Pick<ActionContext, 'db'>,
  firstDay: string,
  lastDay: string,
): Promise<number> {
  const counted = await db
    .selectFrom('pageViewCounts')
    .select((eb) => eb.fn.sum<number>('count').as('count'))
    .where('day', '>=', firstDay)
    .where('day', '<=', lastDay)
    .executeTakeFirstOrThrow()

  return Number(counted.count ?? 0)
}
