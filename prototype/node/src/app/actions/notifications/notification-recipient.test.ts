import { test } from 'node:test'
import assert from 'node:assert/strict'
import { fixtureId } from '../../test/fixture-ids.ts'
import { parseNotificationRow } from './notification-recipient.ts'
import type { Notification } from '../../db/commerce-schema.ts'

const ROW: Notification = {
  id: fixtureId('ntf', 4),
  sellerId: null,
  customerId: null,
  adminId: null,
  subject: 'Sold',
  body: 'Your piece sold.',
  url: '/seller/orders/7',
  createdAt: '2026-08-23T00:00:00.000Z',
  readAt: null,
}

test('a seller row parses to the seller inbox', () => {
  const parsed = parseNotificationRow({ ...ROW, sellerId: fixtureId('sel', 12) })

  assert.equal(parsed.recipientType, 'seller')
  assert.equal(parsed.recipientId, fixtureId('sel', 12))
})

test('a customer row parses to the customer inbox', () => {
  const parsed = parseNotificationRow({ ...ROW, customerId: fixtureId('cus', 31) })

  assert.equal(parsed.recipientType, 'customer')
  assert.equal(parsed.recipientId, fixtureId('cus', 31))
})

test('an admin row parses to the admin inbox', () => {
  const parsed = parseNotificationRow({ ...ROW, adminId: fixtureId('adm', 2) })

  assert.equal(parsed.recipientType, 'admin')
  assert.equal(parsed.recipientId, fixtureId('adm', 2))
})

test('the rest of the row comes through untouched', () => {
  const parsed = parseNotificationRow({ ...ROW, sellerId: fixtureId('sel', 12) })

  assert.deepEqual(parsed, {
    id: fixtureId('ntf', 4),
    recipientType: 'seller',
    recipientId: fixtureId('sel', 12),
    subject: 'Sold',
    body: 'Your piece sold.',
    url: '/seller/orders/7',
    createdAt: '2026-08-23T00:00:00.000Z',
    readAt: null,
  })
})

test('a row naming nobody is a broken database, not an answer', () => {
  assert.throws(() => parseNotificationRow(ROW), TypeError)
})
