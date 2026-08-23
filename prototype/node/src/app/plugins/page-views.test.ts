import { test } from 'node:test'
import assert from 'node:assert/strict'
import type { AppDatabase } from '../db/database.ts'
import { buildTestApp, signInAsAdmin, signInAsSeller } from '../test/build-test-app.ts'

type CountedPage = { site: string; pathPattern: string; day: string; count: number }

async function countedPages(db: AppDatabase): Promise<CountedPage[]> {
  return db
    .selectFrom('pageViewCounts')
    .select(['site', 'pathPattern', 'day', 'count'])
    .orderBy('id')
    .execute()
}

/**
 * The hook runs after the response is sent, so the rows may land a turn of the
 * loop after `inject` resolves. Waiting for the expected number of rows keeps
 * the test off that race without a sleep.
 */
async function settledPages(db: AppDatabase, expected: number): Promise<CountedPage[]> {
  for (let attempt = 0; attempt < 100; attempt += 1) {
    const pages = await countedPages(db)
    if (pages.length >= expected) return pages
    await new Promise((resolve) => setImmediate(resolve))
  }

  return countedPages(db)
}

/** Reads the table once the loop has had every chance to write to it. */
async function quiescedPages(db: AppDatabase): Promise<CountedPage[]> {
  for (let turn = 0; turn < 20; turn += 1) {
    await new Promise((resolve) => setImmediate(resolve))
  }

  return countedPages(db)
}

test('a rendered page is counted against its site, route pattern, and day', async (t) => {
  const testApp = await buildTestApp()
  const { app, db, close } = testApp
  t.after(close)
  const operator = await signInAsAdmin(testApp)

  await app.inject({ method: 'GET', url: '/admin', cookies: operator.cookies })

  assert.deepEqual(await settledPages(db, 1), [
    { site: 'admin', pathPattern: '/admin', day: '2026-08-24', count: 1 },
  ])
})

test('repeat views increment one row and each site keeps its own', async (t) => {
  const testApp = await buildTestApp()
  const { app, db, close } = testApp
  t.after(close)
  const operator = await signInAsAdmin(testApp)
  const seller = await signInAsSeller(testApp)

  await app.inject({ method: 'GET', url: '/admin', cookies: operator.cookies })
  await app.inject({ method: 'GET', url: '/admin', cookies: operator.cookies })
  await app.inject({ method: 'GET', url: '/seller', cookies: seller.cookies })
  await app.inject({ method: 'GET', url: '/' })

  const pages = await settledPages(db, 3)

  assert.deepEqual(
    pages.map((page) => [page.site, page.count]),
    [
      ['admin', 2],
      ['seller', 1],
      ['shop', 1],
    ],
  )
})

test('a redirect, a stylesheet, and a form post are not page views', async (t) => {
  const { app, db, close } = await buildTestApp()
  t.after(close)

  // The guard redirects rather than rendering, so it answers 302 with no HTML.
  await app.inject({ method: 'GET', url: '/admin/account' })
  await app.inject({ method: 'GET', url: '/app.css' })
  await app.inject({ method: 'POST', url: '/admin/login', payload: { email: '' } })

  assert.deepEqual(await quiescedPages(db), [])
})

test('a request that matches no route is counted against nothing', async (t) => {
  const { app, db, close } = await buildTestApp()
  t.after(close)

  await app.inject({ method: 'GET', url: '/admin/nothing-here' })

  assert.deepEqual(await quiescedPages(db), [])
})
