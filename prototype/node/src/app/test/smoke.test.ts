import { test } from 'node:test'
import assert from 'node:assert/strict'
import { listArtwork } from '../sites/shop/storefront-fixtures.ts'
import {
  browseAsAnonymousCustomer,
  buildTestApp,
  signInAsAdmin,
  signInAsSeller,
} from './build-test-app.ts'

/** Where a redirect sent the visitor. */
function destinationOf(response: { headers: Record<string, unknown> }): string {
  return String(response.headers.location ?? '')
}

test('every site serves its own page and they all share one stylesheet', async (t) => {
  const testApp = await buildTestApp()
  const { app, close } = testApp
  t.after(close)

  // The seller portal and the admin site are both behind their guard, so
  // reaching either page means signing in.
  const seller = await signInAsSeller(testApp)
  const operator = await signInAsAdmin(testApp)

  const storefront = await app.inject({ method: 'GET', url: '/' })
  const portal = await app.inject({ method: 'GET', url: '/seller', cookies: seller.cookies })
  const admin = await app.inject({ method: 'GET', url: '/admin', cookies: operator.cookies })

  assert.equal(storefront.statusCode, 200)
  assert.equal(portal.statusCode, 200)
  assert.equal(admin.statusCode, 200)
  assert.match(storefront.body, /Art Store<\/title>/)
  assert.match(portal.body, /Seller portal<\/title>/)
  assert.match(admin.body, /Admin<\/title>/)

  // The stylesheet every layout links is built by the entrypoint, so a serving
  // container answers for it.
  const stylesheet = await app.inject({ method: 'GET', url: '/app.css' })

  assert.equal(stylesheet.statusCode, 200)
  assert.match(stylesheet.headers['content-type'] ?? '', /text\/css/)
})

test('a question on a listing becomes an answer and then a published FAQ', async (t) => {
  const testApp = await buildTestApp()
  const { app, close } = testApp
  t.after(close)

  const seller = await signInAsSeller(testApp)
  const listing = await listArtwork(testApp, { sellerId: seller.id, title: 'Nine Herons' })
  const shopper = await browseAsAnonymousCustomer(testApp)

  // Anyone browsing can ask; no address has been given yet.
  const asked = await app.inject({
    method: 'POST',
    url: `/art/${listing.slug}/questions`,
    cookies: shopper.cookies,
    payload: { body: 'Is this framed?' },
  })

  assert.equal(asked.statusCode, 302)
  const threadPath = destinationOf(asked)
  assert.match(threadPath, /^\/messages\/\d+$/)

  const sellerInbox = await app.inject({ url: '/seller/messages', cookies: seller.cookies })
  assert.equal(sellerInbox.statusCode, 200)
  assert.match(sellerInbox.body, /Is this framed\?/)

  const conversationId = threadPath.split('/').at(-1)
  const sellerThread = await app.inject({
    url: `/seller/messages/${conversationId}`,
    cookies: seller.cookies,
  })
  assert.equal(sellerThread.statusCode, 200)

  const answered = await app.inject({
    method: 'POST',
    url: `/seller/messages/${conversationId}`,
    cookies: seller.cookies,
    payload: { body: 'It ships unframed, ready to hang.' },
  })
  assert.equal(answered.statusCode, 302)

  const published = await app.inject({
    method: 'POST',
    url: `/seller/listings/${listing.id}/faqs`,
    cookies: seller.cookies,
    payload: {
      question: 'Is this framed?',
      answer: 'It ships unframed, ready to hang.',
      redirect_to: `/seller/messages/${conversationId}`,
    },
  })
  assert.equal(published.statusCode, 302)

  // The answer one shopper got is now on the page for the next one.
  const listingPage = await app.inject({ url: `/art/${listing.slug}` })

  assert.equal(listingPage.statusCode, 200)
  assert.match(listingPage.body, /Is this framed\?/)
  assert.match(listingPage.body, /It ships unframed, ready to hang\./)

  // The asker keeps the thread even though they never gave an address.
  const shopperThread = await app.inject({ url: threadPath, cookies: shopper.cookies })

  assert.equal(shopperThread.statusCode, 200)
  assert.match(shopperThread.body, /It ships unframed, ready to hang\./)
})

test('an admin messages a seller and the seller reads it', async (t) => {
  const testApp = await buildTestApp()
  const { app, close } = testApp
  t.after(close)

  const seller = await signInAsSeller(testApp)
  const operator = await signInAsAdmin(testApp)

  const opened = await app.inject({
    method: 'POST',
    url: `/admin/sellers/${seller.id}/messages`,
    cookies: operator.cookies,
  })
  assert.equal(opened.statusCode, 302)

  const adminThreadPath = destinationOf(opened)
  const sent = await app.inject({
    method: 'POST',
    url: adminThreadPath,
    cookies: operator.cookies,
    payload: { body: 'Your payout for last week is on its way.' },
  })
  assert.equal(sent.statusCode, 302)

  // The portal's nav says how much is waiting before the seller opens anything.
  const dashboard = await app.inject({ url: '/seller', cookies: seller.cookies })
  assert.match(dashboard.body, /data-unread-messages="1"/)

  const conversationId = adminThreadPath.split('/').at(-1)
  const sellerThread = await app.inject({
    url: `/seller/messages/${conversationId}`,
    cookies: seller.cookies,
  })

  assert.equal(sellerThread.statusCode, 200)
  assert.match(sellerThread.body, /Your payout for last week is on its way\./)

  const afterReading = await app.inject({ url: '/seller', cookies: seller.cookies })

  assert.doesNotMatch(afterReading.body, /data-unread-messages/)
})
