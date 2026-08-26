import { test } from 'node:test'
import assert from 'node:assert/strict'
import { adminPage, formatJson, formatMoment, idHref, linkedIds } from './page.ts'
import { statusLabel } from '../../core/status-label.ts'

test('every page carries its title and the formatters its tables need', () => {
  const page = adminPage('Sellers', { sellers: [] })

  assert.equal(page.title, 'Sellers')
  assert.deepEqual(page.sellers, [])
  assert.equal(typeof page.formatCents, 'function')
  assert.equal(typeof page.formatMoment, 'function')
  assert.equal(page.statusLabel, statusLabel)
})

test('the title is the page name, whatever the data calls itself', () => {
  assert.equal(adminPage('Sellers', { title: 'Something else' }).title, 'Sellers')
})

test('an instant reads to the minute, and nothing reads as a dash', () => {
  assert.equal(formatMoment('2026-08-24T12:00:00.000Z'), '2026-08-24 12:00')
  assert.equal(formatMoment(null), '—')
})

test('stored JSON text is indented for reading; anything else passes through', () => {
  assert.equal(formatJson('{"order_id":"ord_1"}'), '{\n  "order_id": "ord_1"\n}')
  assert.equal(formatJson('not json'), 'not json')
})

const ULID = '01ARZ3NDEKTSV4RRFFQ69G5FAV'

test('an id links to its detail page, per the admin pages table', () => {
  assert.equal(idHref(`ord_${ULID}`), `/admin/orders/ord_${ULID}`)
  assert.equal(idHref(`cus_${ULID}`), `/admin/customers/cus_${ULID}`)
  assert.equal(idHref(`sel_${ULID}`), `/admin/sellers/sel_${ULID}`)
  assert.equal(idHref(`lst_${ULID}`), `/admin/listings/lst_${ULID}`)
  assert.equal(idHref(`ful_${ULID}`), `/admin/fulfillments/ful_${ULID}`)
  assert.equal(idHref(`obx_${ULID}`), `/admin/outbox/obx_${ULID}`)
  assert.equal(idHref(`cnv_${ULID}`), `/admin/messages/cnv_${ULID}`)
})

test('the two correlation prefixes link back into the log list as filters', () => {
  assert.equal(idHref(`txn_${ULID}`), `/admin/logs?txn=txn_${ULID}`)
  assert.equal(idHref(`ses_${ULID}`), `/admin/logs?session=ses_${ULID}`)
})

test('a prefix with no detail page links nowhere', () => {
  assert.equal(idHref(`msg_${ULID}`), null)
  assert.equal(idHref(`pay_${ULID}`), null)
})

test('linkedIds wraps linkable ids in anchors and escapes everything else', () => {
  const html = linkedIds(`{"order_id":"ord_${ULID}","note":"<b>&"}`)

  assert.equal(
    html,
    `{&quot;order_id&quot;:&quot;<a href="/admin/orders/ord_${ULID}" class="underline">ord_${ULID}</a>&quot;,`
      + `&quot;note&quot;:&quot;&lt;b&gt;&amp;&quot;}`,
  )
})

test('linkedIds leaves an unlinkable id plain', () => {
  assert.equal(linkedIds(`pay_${ULID}`), `pay_${ULID}`)
})
