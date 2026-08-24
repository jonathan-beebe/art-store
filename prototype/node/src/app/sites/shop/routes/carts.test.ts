import { test } from 'node:test'
import assert from 'node:assert/strict'
import { browseAsAnonymousCustomer, buildTestApp, signInAsAdmin, signInAsSeller } from '../../../test/build-test-app.ts'
import { blockCustomer, listArtwork, removeListing } from '../storefront-fixtures.ts'
import { cents } from '../../../core/money.ts'

test('adding a piece puts it on the cart with its quantity and subtotal', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp, 'ada@example.test')
  const customer = await browseAsAnonymousCustomer(testApp)
  await listArtwork(testApp, { sellerId: seller.id, title: 'Harbour at dusk', priceCents: cents(24_000), quantity: 3 })

  const add = await testApp.app.inject({
    method: 'POST',
    url: '/cart/harbour-at-dusk',
    cookies: customer.cookies,
    payload: { quantity: '2' },
  })
  assert.equal(add.statusCode, 302)
  assert.equal(add.headers.location, '/cart')

  const response = await testApp.app.inject({ method: 'GET', url: '/cart', cookies: customer.cookies })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, /Harbour at dusk/)
  assert.match(response.body, /Quantity 2/)
  assert.match(response.body, /\$480\.00/)
})

test('adding the same piece twice sums the quantity', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const customer = await browseAsAnonymousCustomer(testApp)
  await listArtwork(testApp, { sellerId: seller.id, title: 'Harbour at dusk', quantity: 5 })

  await testApp.app.inject({
    method: 'POST',
    url: '/cart/harbour-at-dusk',
    cookies: customer.cookies,
    payload: {},
  })
  await testApp.app.inject({
    method: 'POST',
    url: '/cart/harbour-at-dusk',
    cookies: customer.cookies,
    payload: {},
  })

  const response = await testApp.app.inject({ method: 'GET', url: '/cart', cookies: customer.cookies })

  assert.match(response.body, /Quantity 2/)
})

test('a bodiless POST still adds the listing to the cart', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const customer = await browseAsAnonymousCustomer(testApp)
  await listArtwork(testApp, { sellerId: seller.id, title: 'Harbour at dusk' })

  const add = await testApp.app.inject({
    method: 'POST',
    url: '/cart/harbour-at-dusk',
    cookies: customer.cookies,
  })
  assert.equal(add.statusCode, 302)
  assert.equal(add.headers.location, '/cart')

  const response = await testApp.app.inject({ method: 'GET', url: '/cart', cookies: customer.cookies })
  assert.match(response.body, /Harbour at dusk/)
})

test('removing a piece empties the cart', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const customer = await browseAsAnonymousCustomer(testApp)
  await listArtwork(testApp, { sellerId: seller.id, title: 'Harbour at dusk' })
  await testApp.app.inject({
    method: 'POST',
    url: '/cart/harbour-at-dusk',
    cookies: customer.cookies,
    payload: {},
  })

  const remove = await testApp.app.inject({
    method: 'POST',
    url: '/cart/harbour-at-dusk/remove',
    cookies: customer.cookies,
  })
  assert.equal(remove.statusCode, 302)
  assert.equal(remove.headers.location, '/cart')

  const response = await testApp.app.inject({ method: 'GET', url: '/cart', cookies: customer.cookies })

  assert.match(response.body, /Your cart is empty\./)
  assert.doesNotMatch(response.body, /Harbour at dusk/)
})

test('adding a sold-out listing re-renders the listing with a field-less refusal instead of adding it', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const customer = await browseAsAnonymousCustomer(testApp)
  await listArtwork(testApp, { sellerId: seller.id, title: 'Last copy', status: 'sold' })

  const add = await testApp.app.inject({
    method: 'POST',
    url: '/cart/last-copy',
    cookies: customer.cookies,
    payload: {},
  })

  assert.equal(add.statusCode, 422)
  assert.match(add.body, /data-form-error/)
  assert.match(add.body, /That listing is no longer for sale\./)

  const response = await testApp.app.inject({ method: 'GET', url: '/cart', cookies: customer.cookies })
  assert.match(response.body, /Your cart is empty\./)
})

test('a blocked customer is refused when adding to the cart, told why, and nothing is added', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const admin = await signInAsAdmin(testApp)
  const customer = await browseAsAnonymousCustomer(testApp)
  await listArtwork(testApp, { sellerId: seller.id, title: 'Harbour at dusk' })
  await blockCustomer(testApp, { customerId: customer.id, adminId: admin.id })

  const add = await testApp.app.inject({
    method: 'POST',
    url: '/cart/harbour-at-dusk',
    cookies: customer.cookies,
    payload: {},
  })

  assert.equal(add.statusCode, 302)
  assert.equal(add.headers.location, '/art/harbour-at-dusk')

  const response = await testApp.app.inject({ method: 'GET', url: '/cart', cookies: customer.cookies })
  assert.match(response.body, /data-blocked/)
  assert.match(response.body, /Chargeback fraud\./)
  assert.match(response.body, /Your cart is empty\./)
  assert.doesNotMatch(response.body, /Checkout/)
})

test('an empty cart offers a link back to browsing', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const customer = await browseAsAnonymousCustomer(testApp)

  const response = await testApp.app.inject({ method: 'GET', url: '/cart', cookies: customer.cookies })

  assert.match(response.body, /Your cart is empty\./)
  assert.match(response.body, /href="\/"/)
})

test('a cart with items offers a checkout link and a subtotal', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const customer = await browseAsAnonymousCustomer(testApp)
  await listArtwork(testApp, { sellerId: seller.id, title: 'Harbour at dusk', priceCents: cents(24_000) })
  await testApp.app.inject({
    method: 'POST',
    url: '/cart/harbour-at-dusk',
    cookies: customer.cookies,
    payload: {},
  })

  const response = await testApp.app.inject({ method: 'GET', url: '/cart', cookies: customer.cookies })

  assert.match(response.body, /href="\/checkout"/)
  assert.match(response.body, /Subtotal/)
  assert.match(response.body, /\$240\.00/)
})

test('adding an unknown listing answers 404', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const customer = await browseAsAnonymousCustomer(testApp)

  const response = await testApp.app.inject({
    method: 'POST',
    url: '/cart/nothing-here',
    cookies: customer.cookies,
    payload: {},
  })

  assert.equal(response.statusCode, 404)
})

test('removing an unknown listing answers 404', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const customer = await browseAsAnonymousCustomer(testApp)

  const response = await testApp.app.inject({
    method: 'POST',
    url: '/cart/nothing-here/remove',
    cookies: customer.cookies,
  })

  assert.equal(response.statusCode, 404)
})

test('adding a listing an admin removed answers 404 and leaves the cart empty', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const admin = await signInAsAdmin(testApp)
  const customer = await browseAsAnonymousCustomer(testApp)
  const listing = await listArtwork(testApp, { sellerId: seller.id, title: 'Harbour at dusk' })
  await removeListing(testApp, { listingId: listing.id, adminId: admin.id })

  const add = await testApp.app.inject({
    method: 'POST',
    url: '/cart/harbour-at-dusk',
    cookies: customer.cookies,
    payload: {},
  })

  const items = await testApp.db.selectFrom('cartItems').select('id').execute()

  assert.equal(add.statusCode, 404)
  assert.equal(items.length, 0)
})

test('a refused add leaves no listing event behind', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const customer = await browseAsAnonymousCustomer(testApp)
  const listing = await listArtwork(testApp, { sellerId: seller.id, title: 'Last copy', status: 'sold' })

  await testApp.app.inject({
    method: 'POST',
    url: '/cart/last-copy',
    cookies: customer.cookies,
    payload: {},
  })

  const events = await testApp.db
    .selectFrom('listingEvents')
    .select('id')
    .where('listingId', '=', listing.id)
    .where('eventType', '=', 'cart_add')
    .execute()

  assert.equal(events.length, 0)
})

test('a listing with one in stock takes an add with no quantity field', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp, 'ada@example.test')
  const customer = await browseAsAnonymousCustomer(testApp)
  await listArtwork(testApp, { sellerId: seller.id, title: 'Harbour at dusk', quantity: 1 })

  const add = await testApp.app.inject({
    method: 'POST',
    url: '/cart/harbour-at-dusk',
    cookies: customer.cookies,
    payload: {},
  })

  assert.equal(add.statusCode, 302)
  const cart = await testApp.app.inject({ method: 'GET', url: '/cart', cookies: customer.cookies })
  assert.match(cart.body, /Quantity 1/)
})

test('a quantity that is not a quantity is refused rather than silently read as one', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp, 'ada@example.test')
  const customer = await browseAsAnonymousCustomer(testApp)
  await listArtwork(testApp, { sellerId: seller.id, title: 'Harbour at dusk', quantity: 3 })

  const add = await testApp.app.inject({
    method: 'POST',
    url: '/cart/harbour-at-dusk',
    cookies: customer.cookies,
    payload: { quantity: 'lots' },
  })

  assert.equal(add.statusCode, 422)
  assert.match(add.body, /data-field-error="quantity"[^>]*>Choose a quantity from 1 to 3\./)
  const cart = await testApp.app.inject({ method: 'GET', url: '/cart', cookies: customer.cookies })
  assert.doesNotMatch(cart.body, /Harbour at dusk/)
})

test('a quantity over what remains in stock is refused with the field kept as typed', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp, 'ada@example.test')
  const customer = await browseAsAnonymousCustomer(testApp)
  await listArtwork(testApp, { sellerId: seller.id, title: 'Harbour at dusk', quantity: 3 })

  const add = await testApp.app.inject({
    method: 'POST',
    url: '/cart/harbour-at-dusk',
    cookies: customer.cookies,
    payload: { quantity: '9' },
  })

  assert.equal(add.statusCode, 422)
  assert.match(add.body, /data-field-error="quantity"[^>]*>Choose a quantity from 1 to 3\./)
  assert.match(add.body, /id="quantity"[^>]*value="9"/)
  const cart = await testApp.app.inject({ method: 'GET', url: '/cart', cookies: customer.cookies })
  assert.doesNotMatch(cart.body, /Harbour at dusk/)
})

test('a listing an admin removed after it was carted still shows on the cart, marked unavailable', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const admin = await signInAsAdmin(testApp)
  const customer = await browseAsAnonymousCustomer(testApp)
  const listing = await listArtwork(testApp, { sellerId: seller.id, title: 'Harbour at dusk' })
  await testApp.app.inject({
    method: 'POST',
    url: '/cart/harbour-at-dusk',
    cookies: customer.cookies,
    payload: {},
  })
  await removeListing(testApp, { listingId: listing.id, adminId: admin.id })

  const response = await testApp.app.inject({ method: 'GET', url: '/cart', cookies: customer.cookies })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, /data-unavailable/)
  assert.match(response.body, /Harbour at dusk/)
  assert.match(response.body, /no longer available/)
  assert.doesNotMatch(response.body, /href="\/art\/harbour-at-dusk"/)
})

test('the Remove action still works on a line an admin removed', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const admin = await signInAsAdmin(testApp)
  const customer = await browseAsAnonymousCustomer(testApp)
  const listing = await listArtwork(testApp, { sellerId: seller.id, title: 'Harbour at dusk' })
  await testApp.app.inject({
    method: 'POST',
    url: '/cart/harbour-at-dusk',
    cookies: customer.cookies,
    payload: {},
  })
  await removeListing(testApp, { listingId: listing.id, adminId: admin.id })

  const remove = await testApp.app.inject({
    method: 'POST',
    url: '/cart/harbour-at-dusk/remove',
    cookies: customer.cookies,
  })
  assert.equal(remove.statusCode, 302)
  assert.equal(remove.headers.location, '/cart')

  const response = await testApp.app.inject({ method: 'GET', url: '/cart', cookies: customer.cookies })
  assert.match(response.body, /Your cart is empty\./)
})

test('a line an admin removed after it was carted is left out of the subtotal', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const admin = await signInAsAdmin(testApp)
  const customer = await browseAsAnonymousCustomer(testApp)
  const removed = await listArtwork(testApp, {
    sellerId: seller.id,
    title: 'Harbour at dusk',
    priceCents: cents(24_000),
  })
  await listArtwork(testApp, { sellerId: seller.id, title: 'Low tide', priceCents: cents(6_000) })
  await testApp.app.inject({
    method: 'POST',
    url: '/cart/harbour-at-dusk',
    cookies: customer.cookies,
    payload: {},
  })
  await testApp.app.inject({
    method: 'POST',
    url: '/cart/low-tide',
    cookies: customer.cookies,
    payload: {},
  })
  await removeListing(testApp, { listingId: removed.id, adminId: admin.id })

  const response = await testApp.app.inject({ method: 'GET', url: '/cart', cookies: customer.cookies })

  assert.match(response.body, /\$60\.00/)
  assert.doesNotMatch(response.body, /\$300\.00/)
})
