import { test } from 'node:test'
import assert from 'node:assert/strict'
import { isCountablePageView, pageViewDay } from './page-view.ts'

test('a successful HTML GET is countable', () => {
  assert.equal(isCountablePageView({ method: 'GET', statusCode: 200, contentType: 'text/html' }), true)
})

test('the method check is case-insensitive', () => {
  assert.equal(isCountablePageView({ method: 'get', statusCode: 200, contentType: 'text/html' }), true)
})

test('a non-GET request is not countable', () => {
  assert.equal(isCountablePageView({ method: 'POST', statusCode: 200, contentType: 'text/html' }), false)
})

test('a status below 200 is not countable', () => {
  assert.equal(isCountablePageView({ method: 'GET', statusCode: 101, contentType: 'text/html' }), false)
})

test('a status at or above 300 is not countable', () => {
  assert.equal(isCountablePageView({ method: 'GET', statusCode: 404, contentType: 'text/html' }), false)
})

test('a missing content type is not countable', () => {
  assert.equal(isCountablePageView({ method: 'GET', statusCode: 200, contentType: null }), false)
})

test('a non-HTML content type is not countable', () => {
  assert.equal(isCountablePageView({ method: 'GET', statusCode: 200, contentType: 'application/json' }), false)
})

test('a content type with a charset parameter still counts', () => {
  assert.equal(
    isCountablePageView({ method: 'GET', statusCode: 200, contentType: 'text/html; charset=utf-8' }),
    true,
  )
})

test('pageViewDay reads the UTC day', () => {
  assert.equal(pageViewDay(new Date('2026-08-22T23:59:59.999Z')), '2026-08-22')
})

test('pageViewDay does not shift across a UTC day boundary', () => {
  assert.equal(pageViewDay(new Date('2026-08-23T00:00:00.000Z')), '2026-08-23')
})
