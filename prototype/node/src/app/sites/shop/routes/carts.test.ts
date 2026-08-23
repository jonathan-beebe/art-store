import { test } from 'node:test'
import assert from 'node:assert/strict'
import { browseAsAnonymousCustomer, buildTestApp, signInAsAdmin, signInAsSeller } from '../../../test/build-test-app.ts'
import { blockCustomer, listArtwork } from '../storefront-fixtures.ts'

test('adding a piece puts it on the cart with its quantity and subtotal', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp, 'ada@example.test')
  const customer = await browseAsAnonymousCustomer(testApp)
  await listArtwork(testApp, { sellerId: seller.id, title: 'Harbour at dusk', priceCents: 24_000, quantity: 3 })

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

test('adding a sold-out listing redirects with the alert instead of adding it', async (t) => {
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

  assert.equal(add.statusCode, 302)
  assert.equal(add.headers.location, '/art/last-copy')

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
  await listArtwork(testApp, { sellerId: seller.id, title: 'Harbour at dusk', priceCents: 24_000 })
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
