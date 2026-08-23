import { test } from 'node:test'
import assert from 'node:assert/strict'
import { parseShipmentDetails } from './shipment-details.ts'

test('a carrier and a tracking number parse into a shipment', () => {
  const parsed = parseShipmentDetails({ carrier: 'Royal Mail', trackingNumber: 'RM123456789GB' })

  assert.equal(parsed.ok, true)
  if (!parsed.ok) return
  assert.equal(parsed.value.carrier, 'Royal Mail')
  assert.equal(parsed.value.trackingNumber, 'RM123456789GB')
})

test('surrounding space is not part of either field', () => {
  const parsed = parseShipmentDetails({ carrier: '  Royal Mail  ', trackingNumber: '  RM123456789GB  ' })

  assert.equal(parsed.ok, true)
  if (!parsed.ok) return
  assert.equal(parsed.value.carrier, 'Royal Mail')
  assert.equal(parsed.value.trackingNumber, 'RM123456789GB')
})

test('a shipment with no carrier is refused', () => {
  const parsed = parseShipmentDetails({ carrier: ' ', trackingNumber: 'RM123456789GB' })

  assert.equal(parsed.ok, false)
  if (parsed.ok) return
  assert.deepEqual(parsed.errors, { carrier: 'Enter the carrier.' })
})

test('a shipment with no tracking number is refused', () => {
  const parsed = parseShipmentDetails({ carrier: 'Royal Mail', trackingNumber: '' })

  assert.equal(parsed.ok, false)
  if (parsed.ok) return
  assert.deepEqual(parsed.errors, { trackingNumber: 'Enter the tracking number.' })
})

test('a missing field is an error rather than a blank shipment', () => {
  const parsed = parseShipmentDetails({ carrier: null, trackingNumber: null })

  assert.equal(parsed.ok, false)
  if (parsed.ok) return
  assert.deepEqual(parsed.errors, {
    carrier: 'Enter the carrier.',
    trackingNumber: 'Enter the tracking number.',
  })
})
