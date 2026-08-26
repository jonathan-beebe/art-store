import { test } from 'node:test'
import assert from 'node:assert/strict'
import type { NewLogLine } from '../../../db/logs-schema.ts'
import { logStoreStream, openLogStore, type LogStore } from '../../../log-store.ts'
import { buildTestApp, signInAsAdmin, type TestApp } from '../../../test/build-test-app.ts'
import { storedLogLine } from '../../../test/log-lines.ts'

type LogsTestApp = TestApp & {
  store: LogStore
  seed: (lines: readonly NewLogLine[]) => void
}

/**
 * The whole app over an in-memory log store shared by the ingest writer and
 * the `logsDb` reader — the wiring `docs/log-store.md` § Testing names. The
 * stream's passthrough is pointed at a quiet sink; mocking the process's own
 * stdout would swallow the test runner's protocol.
 */
async function buildLogsTestApp(): Promise<LogsTestApp> {
  const store = openLogStore(':memory:')
  const stream = logStoreStream(store, { stdout: { write: () => true } })
  const testApp = await buildTestApp({ logStore: store, loggerStream: stream })

  return {
    ...testApp,
    store,
    seed: (lines) => {
      for (const line of lines) store.append(line)
      store.flushSync()
    },
    close: async () => {
      await testApp.close()
      store.close()
    },
  }
}

test('a visitor with no admin cookie is sent to sign in from both pages', async (t) => {
  const testApp = await buildLogsTestApp()
  t.after(testApp.close)

  const list = await testApp.app.inject({ method: 'GET', url: '/admin/logs' })
  const story = await testApp.app.inject({ method: 'GET', url: '/admin/logs/requests/req-1' })

  assert.equal(list.statusCode, 302)
  assert.equal(story.statusCode, 302)
})

test('GET /admin/logs lists stored lines with their story columns', async (t) => {
  const testApp = await buildLogsTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  testApp.seed([
    storedLogLine({
      msg: '🎬 GET /checkout',
      event: 'http.request',
      phase: 'will',
      requestId: 'req-list',
      actorType: 'customer',
      actorId: 'cus_01ARZ3NDEKTSV4RRFFQ69G5FAV',
      durationMs: 12,
      data: JSON.stringify({ order_id: 'ord_01ARZ3NDEKTSV4RRFFQ69G5FAV' }),
    }),
  ])

  const response = await testApp.app.inject({ method: 'GET', url: '/admin/logs', cookies: admin.cookies })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, /🎬 GET \/checkout/)
  assert.match(response.body, /data-cell="ts"[^>]*>2026-08-24T12:00:00\.000Z</)
  assert.match(response.body, /http\.request[^<]*·[^<]*will/)
  assert.match(response.body, /href="\/admin\/logs\/requests\/req-list"/)
  assert.match(response.body, /href="\/admin\/customers\/cus_01ARZ3NDEKTSV4RRFFQ69G5FAV"/)
  assert.match(response.body, /href="\/admin\/orders\/ord_01ARZ3NDEKTSV4RRFFQ69G5FAV"/)
})

test('each filter narrows the list', async (t) => {
  const testApp = await buildLogsTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  testApp.seed([
    storedLogLine({ msg: 'the paid line', event: 'order.pay' }),
    storedLogLine({ msg: 'the placed line', event: 'order.place' }),
  ])

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/logs?event=order.pay',
    cookies: admin.cookies,
  })

  assert.match(response.body, /the paid line/)
  assert.doesNotMatch(response.body, /the placed line/)
})

test('the filter form remembers the submitted values', async (t) => {
  const testApp = await buildLogsTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/logs?level=warn&event=order.pay&request=req-9&msg=refund&key=data.order_id&value=ord_x',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, /<option value="warn" selected>/)
  assert.match(response.body, /<option value="order.pay" selected>/)
  assert.match(response.body, /value="req-9"/)
  assert.match(response.body, /value="refund"/)
  assert.match(response.body, /value="data.order_id"/)
  assert.match(response.body, /value="ord_x"/)
})

test('the "all" options submit empty filters, which the list reads as no filter', async (t) => {
  const testApp = await buildLogsTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/logs?level=&phase=&event=&request=&msg=&from=&to=&key=&value=',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 200)
})

test('an unrecognised filter value answers 400', async (t) => {
  const testApp = await buildLogsTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const badLevel = await testApp.app.inject({ method: 'GET', url: '/admin/logs?level=loud', cookies: admin.cookies })
  const badEvent = await testApp.app.inject({ method: 'GET', url: '/admin/logs?event=order.explode', cookies: admin.cookies })
  const badInstant = await testApp.app.inject({ method: 'GET', url: '/admin/logs?from=yesterday', cookies: admin.cookies })
  const badTxn = await testApp.app.inject({ method: 'GET', url: '/admin/logs?txn=ord_01ARZ3NDEKTSV4RRFFQ69G5FAV', cookies: admin.cookies })

  assert.equal(badLevel.statusCode, 400)
  assert.equal(badEvent.statusCode, 400)
  assert.equal(badInstant.statusCode, 400)
  assert.equal(badTxn.statusCode, 400)
})

test('a key that is not a dotted identifier path answers 400', async (t) => {
  const testApp = await buildLogsTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/admin/logs?key=${encodeURIComponent('data..order_id')}`,
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 400)
})

test('a value with no key answers 400', async (t) => {
  const testApp = await buildLogsTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/logs?value=ord_x',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 400)
})

test('a numeric attribute value matches a JSON number', async (t) => {
  const testApp = await buildLogsTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  testApp.seed([
    storedLogLine({ msg: 'the priced line', data: JSON.stringify({ total_cents: 45_000 }) }),
    storedLogLine({ msg: 'the other line', data: JSON.stringify({ total_cents: 9 }) }),
  ])

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/logs?key=data.total_cents&value=45000',
    cookies: admin.cookies,
  })

  assert.match(response.body, /the priced line/)
  assert.doesNotMatch(response.body, /the other line/)
})

test('a key with no value keeps every line naming the attribute', async (t) => {
  const testApp = await buildLogsTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  testApp.seed([
    storedLogLine({ msg: 'the refunding line', data: JSON.stringify({ refund_id: 'rfd-1' }) }),
    storedLogLine({ msg: 'the other line' }),
  ])

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/logs?key=data.refund_id',
    cookies: admin.cookies,
  })

  assert.match(response.body, /the refunding line/)
  assert.doesNotMatch(response.body, /the other line/)
})

test('the level tiles tally over the filters minus level and link with level set', async (t) => {
  const testApp = await buildLogsTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  testApp.seed([
    storedLogLine({ level: 'error', event: 'order.pay' }),
    storedLogLine({ level: 'info', event: 'order.pay' }),
    storedLogLine({ level: 'info', event: 'order.pay' }),
    storedLogLine({ level: 'info', event: 'listing.view' }),
  ])

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/logs?level=error&event=order.pay',
    cookies: admin.cookies,
  })

  assert.match(response.body, /data-stat="level-error"[^]*?data-count[^>]*>1</)
  assert.match(response.body, /data-stat="level-info"[^]*?data-count[^>]*>2</)
  assert.match(response.body, /href="\/admin\/logs\?event=order.pay&amp;level=info"/)
})

test('a full page shows 50 lines and the pager preserves the filters', async (t) => {
  const testApp = await buildLogsTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  testApp.seed(
    Array.from({ length: 51 }, (_, index) =>
      storedLogLine({
        ts: `2026-08-24T12:00:${String(index % 60).padStart(2, '0')}.000Z`,
        event: 'order.pay',
      }),
    ),
  )

  const firstPage = await testApp.app.inject({
    method: 'GET',
    url: '/admin/logs?event=order.pay',
    cookies: admin.cookies,
  })
  assert.equal((firstPage.body.match(/data-line="/g) ?? []).length, 50)
  assert.match(firstPage.body, /href="\/admin\/logs\?event=order.pay&amp;page=2"/)

  const secondPage = await testApp.app.inject({
    method: 'GET',
    url: '/admin/logs?event=order.pay&page=2',
    cookies: admin.cookies,
  })
  assert.equal((secondPage.body.match(/data-line="/g) ?? []).length, 1)
})

test('the story view renders one request in order with its header facts', async (t) => {
  const testApp = await buildLogsTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  testApp.seed([
    storedLogLine({
      ts: '2026-08-24T12:00:00.000Z',
      msg: '🎬 GET /checkout',
      phase: 'will',
      requestId: 'req-story',
      sessionId: 'ses_01ARZ3NDEKTSV4RRFFQ69G5FAV',
      actorType: 'customer',
      actorId: 'cus_01ARZ3NDEKTSV4RRFFQ69G5FAV',
    }),
    storedLogLine({
      ts: '2026-08-24T12:00:01.000Z',
      msg: 'order placed',
      requestId: 'req-story',
      data: JSON.stringify({ order_id: 'ord_01ARZ3NDEKTSV4RRFFQ69G5FAV' }),
    }),
    storedLogLine({
      ts: '2026-08-24T12:00:02.000Z',
      msg: '🟢 GET /checkout',
      phase: 'did',
      requestId: 'req-story',
      durationMs: 34,
    }),
    storedLogLine({ msg: 'elsewhere', requestId: 'req-other' }),
  ])

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/logs/requests/req-story',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 200)
  const opened = response.body.indexOf('🎬 GET /checkout')
  const placed = response.body.indexOf('order placed')
  const closed = response.body.indexOf('🟢 GET /checkout')
  assert.ok(opened !== -1 && opened < placed && placed < closed)
  assert.doesNotMatch(response.body, /elsewhere/)

  assert.match(response.body, /data-stat="lines"[^]*?>3</)
  assert.match(response.body, /data-stat="duration"[^]*?>34</)
  assert.match(response.body, /href="\/admin\/logs\?session=ses_01ARZ3NDEKTSV4RRFFQ69G5FAV"/)
  assert.match(response.body, /href="\/admin\/customers\/cus_01ARZ3NDEKTSV4RRFFQ69G5FAV"/)
  assert.match(response.body, /href="\/admin\/orders\/ord_01ARZ3NDEKTSV4RRFFQ69G5FAV"/)
  assert.match(response.body, /<details open/)
})

test('the story stops at 1,000 lines and says so', async (t) => {
  const testApp = await buildLogsTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  testApp.seed(
    Array.from({ length: 1001 }, (_, index) =>
      storedLogLine({
        ts: `2026-08-24T12:00:00.${String(index % 1000).padStart(3, '0')}Z`,
        requestId: 'req-long',
      }),
    ),
  )

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/logs/requests/req-long',
    cookies: admin.cookies,
  })

  assert.equal((response.body.match(/data-line="/g) ?? []).length, 1000)
  assert.match(response.body, /data-cap-notice[^]*?first 1000 of 1001 lines/)
})

test('a well-formed request id with no stored lines renders the empty state at 200', async (t) => {
  const testApp = await buildLogsTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/logs/requests/req-unknown',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, /retention window/)
})

test('a malformed request id is a segment the route refuses — the 404 page', async (t) => {
  const testApp = await buildLogsTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/logs/requests/not%20a%20request%20id',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 404)
})

test('an empty result renders the empty state, not an empty table', async (t) => {
  const testApp = await buildLogsTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const response = await testApp.app.inject({ method: 'GET', url: '/admin/logs', cookies: admin.cookies })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, /No log lines match these filters\./)
})

test('the domain filter narrows the list to one site\'s requests', async (t) => {
  const testApp = await buildLogsTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  testApp.seed([
    storedLogLine({
      msg: 'the admin will line',
      event: 'http.request',
      phase: 'will',
      requestId: 'req-admin',
      data: JSON.stringify({ method: 'GET', path: '/admin/orders' }),
    }),
    storedLogLine({ msg: 'the admin step', requestId: 'req-admin' }),
    storedLogLine({
      msg: 'the shop will line',
      event: 'http.request',
      phase: 'will',
      requestId: 'req-shop',
      data: JSON.stringify({ method: 'GET', path: '/checkout' }),
    }),
  ])

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/logs?domain=admin',
    cookies: admin.cookies,
  })

  assert.match(response.body, /the admin step/)
  assert.doesNotMatch(response.body, /the shop will line/)
})

test('an unrecognised domain value answers 400', async (t) => {
  const testApp = await buildLogsTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/logs?domain=wholesale',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 400)
})

test('an empty domain value reads as no filter', async (t) => {
  const testApp = await buildLogsTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  testApp.seed([storedLogLine({ msg: 'shown either way' })])

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/logs?domain=',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, /shown either way/)
})

test('the domain filter is placed before level and remembers the submitted value', async (t) => {
  const testApp = await buildLogsTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/logs?domain=seller',
    cookies: admin.cookies,
  })

  assert.match(response.body, /<option value="seller" selected>/)
  const domainIndex = response.body.indexOf('id="domain"')
  const levelIndex = response.body.indexOf('id="level"')
  assert.ok(domainIndex !== -1 && domainIndex < levelIndex)
})

test('the group checkbox shows one row per request, newest first', async (t) => {
  const testApp = await buildLogsTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  testApp.seed([
    storedLogLine({
      ts: '2026-08-24T12:00:00.000Z',
      msg: '🎬 GET /checkout',
      event: 'http.request',
      phase: 'will',
      requestId: 'req-group',
      data: JSON.stringify({ method: 'GET', path: '/checkout' }),
    }),
    storedLogLine({
      ts: '2026-08-24T12:00:01.000Z',
      msg: '🟢 GET /checkout 200',
      event: 'http.request',
      phase: 'did',
      requestId: 'req-group',
      durationMs: 12,
      data: JSON.stringify({ status: 200 }),
    }),
  ])

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/logs?group=1',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.equal((response.body.match(/data-group="/g) ?? []).length, 1)
  assert.match(response.body, /🎬 GET \/checkout/)
  assert.match(response.body, /🟢 GET \/checkout 200/)
})

test('the group checkbox remembers its checked state', async (t) => {
  const testApp = await buildLogsTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/logs?group=1',
    cookies: admin.cookies,
  })

  assert.match(response.body, /id="group"[^>]*checked/)
})

test('an unrecognised group value answers 400', async (t) => {
  const testApp = await buildLogsTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/logs?group=yes',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 400)
})

test('grouped paging counts groups and the pager preserves group=1', async (t) => {
  const testApp = await buildLogsTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  testApp.seed(
    Array.from({ length: 51 }, (_, index) =>
      storedLogLine({
        ts: `2026-08-24T12:00:${String(index % 60).padStart(2, '0')}.000Z`,
        requestId: `req-${index}`,
      }),
    ),
  )

  const firstPage = await testApp.app.inject({
    method: 'GET',
    url: '/admin/logs?group=1',
    cookies: admin.cookies,
  })
  assert.equal((firstPage.body.match(/data-group="/g) ?? []).length, 50)
  assert.match(firstPage.body, /href="\/admin\/logs\?group=1&amp;page=2"/)

  const secondPage = await testApp.app.inject({
    method: 'GET',
    url: '/admin/logs?group=1&page=2',
    cookies: admin.cookies,
  })
  assert.equal((secondPage.body.match(/data-group="/g) ?? []).length, 1)
})

test('group composes with an existing filter: only matching requests group, in full', async (t) => {
  const testApp = await buildLogsTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  testApp.seed([
    storedLogLine({ requestId: 'req-mixed', level: 'info', msg: 'the quiet step' }),
    storedLogLine({ requestId: 'req-mixed', level: 'error', msg: 'the error line' }),
    storedLogLine({ requestId: 'req-quiet', level: 'info', msg: 'never shown' }),
  ])

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/logs?group=1&level=error',
    cookies: admin.cookies,
  })

  assert.equal((response.body.match(/data-group="/g) ?? []).length, 1)
  assert.match(response.body, /the quiet step/)
  assert.match(response.body, /the error line/)
  assert.doesNotMatch(response.body, /never shown/)
})

test('a grouped result with no matches renders the empty state', async (t) => {
  const testApp = await buildLogsTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/logs?group=1',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, /No log lines match these filters\./)
})

test('health-check lines are hidden from the list by default', async (t) => {
  const testApp = await buildLogsTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  testApp.seed([
    storedLogLine({
      msg: 'the health will line',
      event: 'http.request',
      phase: 'will',
      requestId: 'req-health',
      data: JSON.stringify({ method: 'GET', path: '/health' }),
    }),
    storedLogLine({ msg: 'the real traffic line', requestId: 'req-shop' }),
  ])

  const response = await testApp.app.inject({ method: 'GET', url: '/admin/logs', cookies: admin.cookies })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, /the real traffic line/)
  assert.doesNotMatch(response.body, /the health will line/)
})

test('health=1 includes the health-check lines again', async (t) => {
  const testApp = await buildLogsTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  testApp.seed([
    storedLogLine({
      msg: 'the health will line',
      event: 'http.request',
      phase: 'will',
      requestId: 'req-health',
      data: JSON.stringify({ method: 'GET', path: '/health' }),
    }),
    storedLogLine({ msg: 'the real traffic line', requestId: 'req-shop' }),
  ])

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/logs?health=1',
    cookies: admin.cookies,
  })

  assert.match(response.body, /the health will line/)
  assert.match(response.body, /the real traffic line/)
})

test('an empty health value reads as hidden, the default', async (t) => {
  const testApp = await buildLogsTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  testApp.seed([
    storedLogLine({
      msg: 'the health will line',
      event: 'http.request',
      phase: 'will',
      requestId: 'req-health',
      data: JSON.stringify({ method: 'GET', path: '/health' }),
    }),
  ])

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/logs?health=',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.doesNotMatch(response.body, /the health will line/)
})

test('an unrecognised health value answers 400', async (t) => {
  const testApp = await buildLogsTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/logs?health=yes',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 400)
})

test('the health checkbox is unchecked by default and remembers being checked', async (t) => {
  const testApp = await buildLogsTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const hidden = await testApp.app.inject({ method: 'GET', url: '/admin/logs', cookies: admin.cookies })
  assert.doesNotMatch(hidden.body, /id="health"[^>]*checked/)

  const shown = await testApp.app.inject({
    method: 'GET',
    url: '/admin/logs?health=1',
    cookies: admin.cookies,
  })
  assert.match(shown.body, /id="health"[^>]*checked/)
})

test('the level tiles exclude health-check lines by default', async (t) => {
  const testApp = await buildLogsTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  testApp.seed([
    storedLogLine({
      level: 'error',
      event: 'http.request',
      phase: 'will',
      requestId: 'req-health',
      data: JSON.stringify({ method: 'GET', path: '/health' }),
    }),
    storedLogLine({ level: 'info', requestId: 'req-shop' }),
  ])

  const response = await testApp.app.inject({ method: 'GET', url: '/admin/logs', cookies: admin.cookies })

  assert.match(response.body, /data-stat="level-error"[^]*?data-count[^>]*>0</)
  assert.match(response.body, /data-stat="level-info"[^]*?data-count[^>]*>1</)
})

test('a health request\'s story is still addressable by id, list hidden or not', async (t) => {
  const testApp = await buildLogsTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  testApp.seed([
    storedLogLine({
      msg: 'the health will line',
      event: 'http.request',
      phase: 'will',
      requestId: 'req-health',
      data: JSON.stringify({ method: 'GET', path: '/health' }),
    }),
  ])

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/logs/requests/req-health',
    cookies: admin.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, /the health will line/)
})

test('health=1 round-trips through the pager', async (t) => {
  const testApp = await buildLogsTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)
  testApp.seed(
    Array.from({ length: 51 }, (_, index) =>
      storedLogLine({
        ts: `2026-08-24T12:00:${String(index % 60).padStart(2, '0')}.000Z`,
        event: 'http.request',
        phase: 'will',
        requestId: `req-health-${index}`,
        data: JSON.stringify({ method: 'GET', path: '/health' }),
      }),
    ),
  )

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/admin/logs?health=1',
    cookies: admin.cookies,
  })

  assert.equal((response.body.match(/data-line="/g) ?? []).length, 50)
  assert.match(response.body, /href="\/admin\/logs\?health=1&amp;page=2"/)
})

test('a disabled store renders the unavailable empty state at 200 on both pages', async (t) => {
  // Plain `buildTestApp`: `TEST_CONFIG` keeps `LOG_DATABASE_FILE` off, so the
  // app carries no `logsDb` at all.
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const admin = await signInAsAdmin(testApp)

  const list = await testApp.app.inject({ method: 'GET', url: '/admin/logs', cookies: admin.cookies })
  const story = await testApp.app.inject({
    method: 'GET',
    url: '/admin/logs/requests/req-1',
    cookies: admin.cookies,
  })

  assert.equal(list.statusCode, 200)
  assert.match(list.body, /log store is unavailable/)
  assert.equal(story.statusCode, 200)
  assert.match(story.body, /log store is unavailable/)
})
