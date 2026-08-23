import { recordPageView } from '../actions/analytics/record-page-view.ts'
import { fixedClock } from '../clock.ts'
import { PAGE_VIEW_SITES, type PageViewSite } from '../core/analytics/page-view-site.ts'
import type { AppDatabase } from './database.ts'

const DAYS_OF_HISTORY = 14
const DAY_MS = 24 * 60 * 60 * 1000

const PATH_PATTERNS_BY_SITE = {
  shop: ['/', '/art/:slug', '/cart'],
  seller: ['/seller', '/seller/listings'],
  admin: ['/admin', '/admin/sellers'],
} as const satisfies Record<PageViewSite, readonly string[]>

/** A varied but deterministic hit count, so no two rows read identically. */
function hitsFor(daysAgo: number, patternIndex: number): number {
  return 2 + ((daysAgo * 3 + patternIndex * 5) % 7)
}

/** Fills `page_view_counts` for the 14 days up to `now`, across all three
 * sites, by calling the same action the live traffic hook calls. Returns how
 * many (site, pattern, day) rows exist afterward. */
export async function seedPageViews(db: AppDatabase, now: Date): Promise<number> {
  for (let daysAgo = 0; daysAgo < DAYS_OF_HISTORY; daysAgo += 1) {
    await recordDay(db, new Date(now.getTime() - daysAgo * DAY_MS), daysAgo)
  }

  const rows = await db.selectFrom('pageViewCounts').select('id').execute()
  return rows.length
}

async function recordDay(db: AppDatabase, day: Date, daysAgo: number): Promise<void> {
  const context = { db, clock: fixedClock(day) }
  let patternIndex = 0

  for (const site of PAGE_VIEW_SITES) {
    for (const pathPattern of PATH_PATTERNS_BY_SITE[site]) {
      const hits = hitsFor(daysAgo, patternIndex)
      for (let hit = 0; hit < hits; hit += 1) {
        await recordPageView(context, { site, pathPattern })
      }
      patternIndex += 1
    }
  }
}
