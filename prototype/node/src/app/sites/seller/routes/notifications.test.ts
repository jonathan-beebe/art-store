import { test } from 'node:test'
import assert from 'node:assert/strict'
import { buildTestApp, signInAsSeller } from '../../../test/build-test-app.ts'
import { createTestNotification } from '../test-fixtures.ts'

test('a signed-out visitor reads no notifications', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)

  const response = await testApp.app.inject({ method: 'GET', url: '/seller/notifications' })

  assert.equal(response.statusCode, 302)
})

test('a notification id that is not a number is not found', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)

  const response = await testApp.app.inject({
    method: 'POST',
    url: '/seller/notifications/not-a-number/read',
    cookies: seller.cookies,
  })

  assert.equal(response.statusCode, 404)
})

test('it lists the seller notifications newest first', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  await createTestNotification(testApp, seller.id, { subject: 'Older' })
  await createTestNotification(testApp, seller.id, { subject: 'Newer' })

  const response = await testApp.app.inject({ method: 'GET', url: '/seller/notifications', cookies: seller.cookies })

  assert.equal(response.statusCode, 200)
  assert.equal((response.body.match(/data-notification="/g) ?? []).length, 2)
  const newerIndex = response.body.indexOf('Newer')
  const olderIndex = response.body.indexOf('Older')
  assert.ok(newerIndex < olderIndex, 'the newest notification renders first')
})

test('the index pages at 25 and links the next page', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const notifications = []
  for (let i = 0; i < 26; i += 1) {
    notifications.push(await createTestNotification(testApp, seller.id, { subject: `Notice ${i}` }))
  }
  const newest = notifications.at(-1)!
  const oldest = notifications[0]!

  const firstPage = await testApp.app.inject({ method: 'GET', url: '/seller/notifications', cookies: seller.cookies })

  assert.equal(firstPage.statusCode, 200)
  assert.equal((firstPage.body.match(/data-notification="/g) ?? []).length, 25)
  assert.match(firstPage.body, new RegExp(`data-notification="${newest.id}"`))
  assert.doesNotMatch(firstPage.body, new RegExp(`data-notification="${oldest.id}"`))
  assert.match(firstPage.body, /href="\/seller\/notifications\?page=2"/)

  const secondPage = await testApp.app.inject({
    method: 'GET',
    url: '/seller/notifications?page=2',
    cookies: seller.cookies,
  })

  assert.equal((secondPage.body.match(/data-notification="/g) ?? []).length, 1)
  assert.match(secondPage.body, new RegExp(`data-notification="${oldest.id}"`))
})

test('an unread notification is badged and offers the mark-read form', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const unread = await createTestNotification(testApp, seller.id)
  const read = await createTestNotification(testApp, seller.id, { subject: 'Seen already', read: true })

  const response = await testApp.app.inject({ method: 'GET', url: '/seller/notifications', cookies: seller.cookies })

  const unreadBlock = new RegExp(`data-notification="${unread.id}"[\\s\\S]*?</li>`).exec(response.body)?.[0] ?? ''
  assert.match(unreadBlock, /data-unread/)
  assert.match(unreadBlock, new RegExp(`action="/seller/notifications/${unread.id}/read"`))

  const readBlock = new RegExp(`data-notification="${read.id}"[\\s\\S]*?</li>`).exec(response.body)?.[0] ?? ''
  assert.doesNotMatch(readBlock, /data-unread/)
  assert.doesNotMatch(readBlock, /<form/)
})

test("another seller's notifications stay off the page", async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const rival = await signInAsSeller(testApp, 'rival@example.com')
  await createTestNotification(testApp, rival.id, { subject: 'Rival notice' })

  const response = await testApp.app.inject({ method: 'GET', url: '/seller/notifications', cookies: seller.cookies })

  assert.doesNotMatch(response.body, /data-notification="/)
  assert.match(response.body, /Nothing yet\./)
})

test('a signed-out visitor marks nothing read', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const notification = await createTestNotification(testApp, seller.id)

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/seller/notifications/${notification.id}/read`,
  })

  assert.equal(response.statusCode, 302)
  assert.match(response.headers.location ?? '', /^\/seller\/login/)
  const unchanged = await testApp.db
    .selectFrom('notifications')
    .selectAll()
    .where('id', '=', notification.id)
    .executeTakeFirstOrThrow()
  assert.equal(unchanged.readAt, null)
})

test('marking a notification read clears it from the unread count', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const notification = await createTestNotification(testApp, seller.id)

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/seller/notifications/${notification.id}/read`,
    cookies: seller.cookies,
  })

  assert.equal(response.statusCode, 302)
  assert.equal(response.headers.location, '/seller/notifications')
  const updated = await testApp.db
    .selectFrom('notifications')
    .selectAll()
    .where('id', '=', notification.id)
    .executeTakeFirstOrThrow()
  assert.notEqual(updated.readAt, null)

  const flashCookie = response.cookies.find((cookie) => cookie.name === 'flash')
  const follow = await testApp.app.inject({
    method: 'GET',
    url: '/seller/notifications',
    cookies: { ...seller.cookies, ...(flashCookie ? { flash: String(flashCookie.value) } : {}) },
  })
  assert.match(follow.body, /Marked as read\./)
})

test("marking another seller's notification read is not found", async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const rival = await signInAsSeller(testApp, 'rival@example.com')
  const rivalNotification = await createTestNotification(testApp, rival.id)

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/seller/notifications/${rivalNotification.id}/read`,
    cookies: seller.cookies,
  })

  assert.equal(response.statusCode, 404)
  const unchanged = await testApp.db
    .selectFrom('notifications')
    .selectAll()
    .where('id', '=', rivalNotification.id)
    .executeTakeFirstOrThrow()
  assert.equal(unchanged.readAt, null)
})
