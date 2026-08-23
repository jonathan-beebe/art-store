import { test } from 'node:test'
import assert from 'node:assert/strict'
import { customerName } from './participant-name.ts'

test('a named customer reads by their name', () => {
  assert.equal(customerName({ id: 4, name: 'Casey Whitfield', email: 'casey@example.test' }), 'Casey Whitfield')
})

test('a verified customer with no name reads by their address', () => {
  assert.equal(customerName({ id: 4, name: null, email: 'casey@example.test' }), 'casey@example.test')
})

test('an anonymous customer reads by their row', () => {
  assert.equal(customerName({ id: 4, name: null, email: null }), 'Guest #4')
})

test('blank name and address fall through to the row', () => {
  assert.equal(customerName({ id: 7, name: '  ', email: '  ' }), 'Guest #7')
})
