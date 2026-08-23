import { test } from 'node:test'
import assert from 'node:assert/strict'
import { addCents, dollarsInputValue, multiplyCents, percentOfCents, formatCents, parseDollars } from './money.ts'

test('addCents sums two amounts', () => {
  assert.equal(addCents(1000, 500), 1500)
})

test('addCents rejects a non-integer amount', () => {
  assert.throws(() => addCents(12.5, 100), /12\.5/)
})

test('addCents rejects a non-finite amount', () => {
  assert.throws(() => addCents(NaN, 100), Error)
})

test('multiplyCents scales an amount by a whole quantity', () => {
  assert.equal(multiplyCents(1000, 3), 3000)
})

test('multiplyCents scales a negative amount', () => {
  assert.equal(multiplyCents(-1000, 3), -3000)
})

test('multiplyCents rejects a fractional factor', () => {
  assert.throws(() => multiplyCents(1000, 1.5), /1\.5/)
})

test('multiplyCents rejects a non-integer amount', () => {
  assert.throws(() => multiplyCents(12.5, 2), /12\.5/)
})

test('percentOfCents takes a whole share', () => {
  assert.equal(percentOfCents(1000, 10), 100)
})

test('percentOfCents rounds down below a half-cent', () => {
  assert.equal(percentOfCents(333, 10), 33)
})

test('percentOfCents rounds a half-cent away from zero', () => {
  assert.equal(percentOfCents(155, 10), 16)
})

test('percentOfCents rounds a negative half-cent away from zero', () => {
  assert.equal(percentOfCents(-155, 10), -16)
})

test('percentOfCents rounds a half-cent away from zero at a larger amount', () => {
  assert.equal(percentOfCents(1005, 10), 101)
})

test('percentOfCents rounds a negative half-cent away from zero at a larger amount', () => {
  assert.equal(percentOfCents(-1005, 10), -101)
})

test('percentOfCents rejects a non-integer amount', () => {
  assert.throws(() => percentOfCents(12.5, 10), /12\.5/)
})

test('percentOfCents rejects a non-finite percent', () => {
  assert.throws(() => percentOfCents(1000, NaN), Error)
})

test('formatCents writes dollars and cents', () => {
  assert.equal(formatCents(1234), '$12.34')
})

test('formatCents pads cents under a dime', () => {
  assert.equal(formatCents(5), '$0.05')
})

test('formatCents formats zero', () => {
  assert.equal(formatCents(0), '$0.00')
})

test('formatCents separates thousands', () => {
  assert.equal(formatCents(1234567), '$12,345.67')
})

test('formatCents separates millions', () => {
  assert.equal(formatCents(100_000_000), '$1,000,000.00')
})

test('formatCents puts the sign before the currency symbol', () => {
  assert.equal(formatCents(-1234), '-$12.34')
})

test('formatCents rejects a non-integer amount', () => {
  assert.throws(() => formatCents(12.5), /12\.5/)
})

test('parseDollars parses dollars and cents', () => {
  assert.equal(parseDollars('12.34'), 1234)
})

test('parseDollars parses a whole dollar amount', () => {
  assert.equal(parseDollars('12'), 1200)
})

test('parseDollars parses thousands separators', () => {
  assert.equal(parseDollars('1,234.56'), 123456)
})

test('parseDollars parses a leading currency symbol', () => {
  assert.equal(parseDollars('$12.50'), 1250)
})

test('parseDollars parses a negative amount', () => {
  assert.equal(parseDollars('-12.34'), -1234)
})

test('parseDollars ignores surrounding whitespace', () => {
  assert.equal(parseDollars('  12.34  '), 1234)
})

test('parseDollars rejects an empty string', () => {
  assert.throws(() => parseDollars(''), Error)
})

test('parseDollars rejects text that is not an amount', () => {
  assert.throws(() => parseDollars('twelve dollars'), /twelve dollars/)
})

test('parseDollars rejects a fraction of a cent', () => {
  assert.throws(() => parseDollars('12.345'), /12\.345/)
})

test('parseDollars rejects more than one decimal point', () => {
  assert.throws(() => parseDollars('12.3.4'), /12\.3\.4/)
})

test('parseDollars rejects a bare minus sign', () => {
  assert.throws(() => parseDollars('-'), Error)
})

test('dollarsInputValue writes cents as a plain decimal amount', () => {
  assert.equal(dollarsInputValue(45_000), '450.00')
})

test('dollarsInputValue pads a fractional amount under ten cents', () => {
  assert.equal(dollarsInputValue(105), '1.05')
})

test('dollarsInputValue writes zero as 0.00', () => {
  assert.equal(dollarsInputValue(0), '0.00')
})

test('dollarsInputValue leaves out the thousands separators formatCents adds', () => {
  assert.equal(dollarsInputValue(1_234_567), '12345.67')
})
