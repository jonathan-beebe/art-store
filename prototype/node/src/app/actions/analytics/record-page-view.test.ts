import { test } from 'node:test'
import assert from 'node:assert/strict'
import { recordPageView } from './record-page-view.ts'
import { openCommerceWorld } from '../../test/commerce-world.ts'

test('the first hit of a day writes the row', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  await recordPageView(world.context, { site: 'shop', pathPattern: '/art/:slug' })

  const rows = await world.db.selectFrom('pageViewCounts').selectAll().execute()

  assert.deepEqual(
    rows.map(({ site, pathPattern, day, count }) => ({ site, pathPattern, day, count })),
    [{ site: 'shop', pathPattern: '/art/:slug', day: '2026-08-20', count: 1 }],
  )
})

test('later hits of the same day, site, and pattern increment one row', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  await recordPageView(world.context, { site: 'shop', pathPattern: '/art/:slug' })
  await recordPageView(world.context, { site: 'shop', pathPattern: '/art/:slug' })
  await recordPageView(world.context, { site: 'shop', pathPattern: '/art/:slug' })

  const rows = await world.db.selectFrom('pageViewCounts').selectAll().execute()

  assert.equal(rows.length, 1)
  assert.equal(rows[0]?.count, 3)
})

test('a different site, pattern, or day counts separately', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  await recordPageView(world.context, { site: 'shop', pathPattern: '/art/:slug' })
  await recordPageView(world.context, { site: 'admin', pathPattern: '/art/:slug' })
  await recordPageView(world.context, { site: 'shop', pathPattern: '/' })

  world.travelTo(new Date('2026-08-21T09:00:00.000Z'))
  await recordPageView(world.context, { site: 'shop', pathPattern: '/art/:slug' })

  const rows = await world.db.selectFrom('pageViewCounts').selectAll().orderBy('id').execute()

  assert.equal(rows.length, 4)
  assert.deepEqual(
    rows.map((row) => [row.site, row.pathPattern, row.day, row.count]),
    [
      ['shop', '/art/:slug', '2026-08-20', 1],
      ['admin', '/art/:slug', '2026-08-20', 1],
      ['shop', '/', '2026-08-20', 1],
      ['shop', '/art/:slug', '2026-08-21', 1],
    ],
  )
})
