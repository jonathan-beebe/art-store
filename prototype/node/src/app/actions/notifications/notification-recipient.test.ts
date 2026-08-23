import { test } from 'node:test'
import assert from 'node:assert/strict'
import { parseNotificationRow } from './notification-recipient.ts'
import type { Notification } from '../../db/commerce-schema.ts'

const ROW: Notification = {
  id: 4,
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
  const parsed = parseNotificationRow({ ...ROW, sellerId: 12 })

  assert.equal(parsed.recipientType, 'seller')
  assert.equal(parsed.recipientId, 12)
})

test('a customer row parses to the customer inbox', () => {
  const parsed = parseNotificationRow({ ...ROW, customerId: 31 })

  assert.equal(parsed.recipientType, 'customer')
  assert.equal(parsed.recipientId, 31)
})

test('an admin row parses to the admin inbox', () => {
  const parsed = parseNotificationRow({ ...ROW, adminId: 2 })

  assert.equal(parsed.recipientType, 'admin')
  assert.equal(parsed.recipientId, 2)
})

test('the rest of the row comes through untouched', () => {
  const parsed = parseNotificationRow({ ...ROW, sellerId: 12 })

  assert.deepEqual(parsed, {
    id: 4,
    recipientType: 'seller',
    recipientId: 12,
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
