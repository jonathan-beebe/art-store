import { test } from 'node:test'
import assert from 'node:assert/strict'
import { fixtureId } from '../../test/fixture-ids.ts'
import { markNotificationRead } from './mark-notification-read.ts'
import { notify } from './notify.ts'
import { itemSoldMessage } from '../../core/notifications/notification-message.ts'
import { createSeller, openCommerceWorld } from '../../test/commerce-world.ts'
import { cents } from '../../core/money.ts'

test('it stamps readAt', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const notification = await notify(context, {
    recipientType: 'seller',
    recipientId: shop,
    message: itemSoldMessage(fixtureId('ord', 7), cents(40_500)),
  })

  const read = await markNotificationRead(context, notification.id)

  assert.notEqual(read.readAt, null)
})

test('reading an already-read notification keeps the first moment', async (t) => {
  const world = await openCommerceWorld()
  t.after(world.close)
  const { context } = world

  const shop = await createSeller(context)
  const notification = await notify(context, {
    recipientType: 'seller',
    recipientId: shop,
    message: itemSoldMessage(fixtureId('ord', 7), cents(40_500)),
  })

  const firstRead = await markNotificationRead(context, notification.id)
  world.travelTo(new Date('2026-08-25T09:00:00.000Z'))
  const secondRead = await markNotificationRead(context, notification.id)

  assert.equal(secondRead.readAt, firstRead.readAt)
})
