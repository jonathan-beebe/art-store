import { test } from 'node:test'
import assert from 'node:assert/strict'
import { pageViewTotals, pageViewsByDay, pageViewsByPattern } from './page-view-report.ts'
import { recordPageView } from '../../../actions/analytics/record-page-view.ts'
import { openCommerceWorld, type CommerceWorld } from '../../../test/commerce-world.ts'

async function recordTraffic(world: CommerceWorld): Promise<void> {
  await recordPageView(world.context, { site: 'shop', pathPattern: '/' })
  await recordPageView(world.context, { site: 'shop', pathPattern: '/' })
  await recordPageView(world.context, { site: 'shop', pathPattern: '/art/:slug' })

  world.travelTo(new Date('2026-08-24T09:00:00.000Z'))
  await recordPageView(world.context, { site: 'shop', pathPattern: '/' })
  await recordPageView(world.context, { site: 'admin', pathPattern: '/admin' })
}

test('a platform nobody visited reports nothing', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  assert.deepEqual(await pageViewTotals(world.context, '2026-08-24'), {
    todayCount: 0,
    weekCount: 0,
  })
  assert.deepEqual(await pageViewsByDay(world.context), [])
  assert.deepEqual(await pageViewsByPattern(world.context), [])
})

test('today is the day asked for, and the week is the seven days ending there', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  await recordTraffic(world)

  assert.deepEqual(await pageViewTotals(world.context, '2026-08-24'), {
    todayCount: 2,
    weekCount: 5,
  })
  // 2026-08-20 has fallen out of the seven days ending on the 27th.
  assert.deepEqual(await pageViewTotals(world.context, '2026-08-27'), {
    todayCount: 0,
    weekCount: 2,
  })
})

test('days come back newest first', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  await recordTraffic(world)

  assert.deepEqual(await pageViewsByDay(world.context), [
    { day: '2026-08-24', count: 2 },
    { day: '2026-08-20', count: 3 },
  ])
})

test('patterns come back busiest first, and each site keeps its own', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)

  await recordTraffic(world)

  assert.deepEqual(await pageViewsByPattern(world.context), [
    { site: 'shop', pathPattern: '/', count: 3 },
    { site: 'admin', pathPattern: '/admin', count: 1 },
    { site: 'shop', pathPattern: '/art/:slug', count: 1 },
  ])
})
