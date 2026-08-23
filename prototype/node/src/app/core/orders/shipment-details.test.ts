import { test } from 'node:test'
import assert from 'node:assert/strict'
import { parseShipmentDetails, isShipmentComplete } from './shipment-details.ts'

test('a carrier and a tracking number are complete', () => {
  const details = parseShipmentDetails({ carrier: 'Royal Mail', trackingNumber: 'RM123456789GB' })

  assert.equal(isShipmentComplete(details), true)
})

test('surrounding space is not part of either field', () => {
  const details = parseShipmentDetails({ carrier: '  Royal Mail  ', trackingNumber: '  RM123456789GB  ' })

  assert.equal(details.carrier, 'Royal Mail')
  assert.equal(details.trackingNumber, 'RM123456789GB')
})

test('a shipment with no carrier is incomplete', () => {
  const details = parseShipmentDetails({ carrier: ' ', trackingNumber: 'RM123456789GB' })

  assert.equal(isShipmentComplete(details), false)
})

test('a shipment with no tracking number is incomplete', () => {
  const details = parseShipmentDetails({ carrier: 'Royal Mail', trackingNumber: '' })

  assert.equal(isShipmentComplete(details), false)
})

test('a missing field is incomplete rather than an error', () => {
  const details = parseShipmentDetails({ carrier: null, trackingNumber: null })

  assert.equal(isShipmentComplete(details), false)
  assert.equal(details.carrier, '')
  assert.equal(details.trackingNumber, '')
})
