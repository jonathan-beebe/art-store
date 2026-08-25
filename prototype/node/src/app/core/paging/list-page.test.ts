import { test } from 'node:test'
import assert from 'node:assert/strict'
import { listPage } from './list-page.ts'

test('the first page starts at the beginning', () => {
  const page = listPage({ requested: 1, size: 12, totalCount: 30 })

  assert.equal(page.offset, 0)
  assert.equal(page.limit, 12)
})

test('a later page skips the pages before it', () => {
  assert.equal(listPage({ requested: 3, size: 12, totalCount: 30 }).offset, 24)
})

test('it counts the pages the collection fills', () => {
  assert.equal(listPage({ requested: 1, size: 12, totalCount: 25 }).count, 3)
  assert.equal(listPage({ requested: 1, size: 12, totalCount: 24 }).count, 2)
})

test('an empty collection still has one page', () => {
  const page = listPage({ requested: 1, size: 12, totalCount: 0 })

  assert.equal(page.count, 1)
  assert.ok(page.isFirst)
  assert.ok(page.isLast)
})

test('a page past the end lands on the last one', () => {
  assert.equal(listPage({ requested: 99, size: 12, totalCount: 30 }).number, 3)
})

test('a page before the start lands on the first one', () => {
  assert.equal(listPage({ requested: 0, size: 12, totalCount: 30 }).number, 1)
})

test('input that is not a number lands on the first page', () => {
  assert.equal(listPage({ requested: null, size: 12, totalCount: 30 }).number, 1)
  assert.equal(listPage({ requested: 'second', size: 12, totalCount: 30 }).number, 1)
  assert.equal(listPage({ requested: undefined, size: 12, totalCount: 30 }).number, 1)
})

test('a middle page has a page on each side', () => {
  const page = listPage({ requested: '2', size: 12, totalCount: 30 })

  assert.equal(page.isFirst, false)
  assert.equal(page.isLast, false)
  assert.equal(page.previousNumber, 1)
  assert.equal(page.nextNumber, 3)
})

test('it refuses a size that holds nothing', () => {
  assert.throws(() => listPage({ requested: 1, size: 0, totalCount: 30 }), RangeError)
})

test('it refuses a negative count', () => {
  assert.throws(() => listPage({ requested: 1, size: 12, totalCount: -1 }), RangeError)
})
