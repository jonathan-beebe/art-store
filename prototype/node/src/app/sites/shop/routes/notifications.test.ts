import { test } from 'node:test'
import assert from 'node:assert/strict'
import { notify } from '../../../actions/notifications/notify.ts'
import {
  newMessageMessage,
  orderShippedMessage,
} from '../../../core/notifications/notification-message.ts'
import { buildTestApp, signInAsCustomer } from '../../../test/build-test-app.ts'
import { fixtureId } from '../../../test/fixture-ids.ts'

test('the account page shows the signed-in address and the customer notifications, newest first', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp, 'buyer@example.test')
  await notify(testApp, {
    recipientType: 'customer',
    recipientId: customer.id,
    message: newMessageMessage('Harbour at dusk'),
  })
  await notify(testApp, {
    recipientType: 'customer',
    recipientId: customer.id,
    message: orderShippedMessage(fixtureId('ord', 7), 'UPS', '1Z999', '/orders/7'),
  })

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/account',
    cookies: customer.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, /buyer@example\.test/)
  const shippedAt = response.body.indexOf('Order shipped')
  const messageAt = response.body.indexOf('New message')
  assert.ok(shippedAt !== -1 && messageAt !== -1)
  assert.ok(shippedAt < messageAt, 'the newer notification prints before the older one')
  assert.match(response.body, /href="\/orders\/7"/)
})

test('the account page says nothing has arrived yet when there are no notifications', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/account',
    cookies: customer.cookies,
  })

  assert.match(response.body, /Nothing yet\. Order updates land here\./)
})

test('marking a notification read stamps it, redirects home, and drops its button', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const customer = await signInAsCustomer(testApp)
  const notification = await notify(testApp, {
    recipientType: 'customer',
    recipientId: customer.id,
    message: newMessageMessage('Harbour at dusk'),
  })

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/account/notifications/${notification.id}/read`,
    cookies: customer.cookies,
  })

  assert.equal(response.statusCode, 302)
  assert.equal(response.headers.location, '/account')

  const stored = await testApp.db
    .selectFrom('notifications')
    .selectAll()
    .where('id', '=', notification.id)
    .executeTakeFirstOrThrow()
  assert.notEqual(stored.readAt, null)

  const after = await testApp.app.inject({
    method: 'GET',
    url: '/account',
    cookies: customer.cookies,
  })
  assert.doesNotMatch(after.body, /Mark as read/)
})

test('another customer cannot mark someone else’s notification read', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const owner = await signInAsCustomer(testApp, 'owner@example.test')
  const intruder = await signInAsCustomer(testApp, 'intruder@example.test')
  const notification = await notify(testApp, {
    recipientType: 'customer',
    recipientId: owner.id,
    message: newMessageMessage('Harbour at dusk'),
  })

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/account/notifications/${notification.id}/read`,
    cookies: intruder.cookies,
  })

  assert.equal(response.statusCode, 404)

  const stored = await testApp.db
    .selectFrom('notifications')
    .selectAll()
    .where('id', '=', notification.id)
    .executeTakeFirstOrThrow()
  assert.equal(stored.readAt, null)
})

test('an anonymous visitor asking for the account page is sent to sign in', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const response = await testApp.app.inject({ method: 'GET', url: '/account' })

  assert.equal(response.statusCode, 302)
  assert.match(response.headers.location ?? '', /^\/login/)
})
