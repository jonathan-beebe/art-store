import { test } from 'node:test'
import assert from 'node:assert/strict'
import { adminPage, formatMoment } from './page.ts'
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
