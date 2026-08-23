import { test } from 'node:test'
import assert from 'node:assert/strict'
import { buildTestApp, signInAsAdmin, signInAsSeller } from '../../../test/build-test-app.ts'
import { listArtwork, removeListing } from '../storefront-fixtures.ts'

test('the storefront home page renders in the storefront layout', async (t) => {
  const { app, close } = await buildTestApp()
  t.after(close)

  const response = await app.inject({ method: 'GET', url: '/' })

  assert.equal(response.statusCode, 200)
  assert.match(response.headers['content-type'] ?? '', /text\/html/)
  assert.match(response.body, /<title>Original art — Art Store<\/title>/)
  assert.match(response.body, /href="\/app\.css"/)
})

test('the storefront layout prints a flashed magic link in the debug alert', async (t) => {
  const { app, close } = await buildTestApp()
  t.after(close)
  const link = 'http://localhost:3000/auth/magic/token-from-the-flash'

  const response = await app.inject({
    method: 'GET',
    url: '/',
    cookies: { flash: app.signCookie(JSON.stringify({ debugMagicLink: link })) },
  })

  assert.match(response.body, /role="alert"/)
  assert.match(response.body, new RegExp(link))
})

test('the grid shows art for sale with its picture, shop, and price', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp, 'ada@example.test')
  await listArtwork(testApp, { sellerId: seller.id, title: 'Harbour at dusk', priceCents: 24_000 })

  const response = await testApp.app.inject({ method: 'GET', url: '/' })

  assert.match(response.body, /Harbour at dusk/)
  assert.match(response.body, /href="\/art\/harbour-at-dusk"/)
  assert.match(response.body, /\$240\.00/)
  assert.match(response.body, /ada/)
  assert.match(response.body, /data:image\/svg\+xml;base64,/)
})

test('the grid leaves out art that is not on the storefront', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const admin = await signInAsAdmin(testApp)
  await listArtwork(testApp, { sellerId: seller.id, title: 'Unfinished sketch', status: 'draft' })
  await listArtwork(testApp, { sellerId: seller.id, title: 'Retired print', status: 'archived' })
  await listArtwork(testApp, { sellerId: seller.id, title: 'Last copy', status: 'sold' })
  const removed = await listArtwork(testApp, { sellerId: seller.id, title: 'Taken down' })
  await removeListing(testApp, { listingId: removed.id, adminId: admin.id })

  const response = await testApp.app.inject({ method: 'GET', url: '/' })

  assert.doesNotMatch(response.body, /Unfinished sketch/)
  assert.doesNotMatch(response.body, /Retired print/)
  assert.doesNotMatch(response.body, /Last copy/)
  assert.doesNotMatch(response.body, /Taken down/)
  assert.match(response.body, /No art matches that yet/)
})

test('a search narrows the grid by title, description, and medium', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  await listArtwork(testApp, {
    sellerId: seller.id,
    title: 'Harbour at dusk',
    description: 'Painted from the quay.',
    medium: 'Oil on canvas',
  })
  await listArtwork(testApp, {
    sellerId: seller.id,
    title: 'Kiln study',
    description: 'Thrown and fired twice.',
    medium: 'Stoneware',
  })

  const byTitle = await testApp.app.inject({ method: 'GET', url: '/?q=harbour' })
  assert.match(byTitle.body, /Harbour at dusk/)
  assert.doesNotMatch(byTitle.body, /Kiln study/)
  assert.match(byTitle.body, /Art matching/)

  const byDescription = await testApp.app.inject({ method: 'GET', url: '/?q=quay' })
  assert.match(byDescription.body, /Harbour at dusk/)
  assert.doesNotMatch(byDescription.body, /Kiln study/)

  const byMedium = await testApp.app.inject({ method: 'GET', url: '/?q=stoneware' })
  assert.match(byMedium.body, /Kiln study/)
  assert.doesNotMatch(byMedium.body, /Harbour at dusk/)
})

test('the medium filter offers the media on sale and narrows to one', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  await listArtwork(testApp, { sellerId: seller.id, title: 'Harbour at dusk', medium: 'Oil on canvas' })
  await listArtwork(testApp, { sellerId: seller.id, title: 'Kiln study', medium: 'Stoneware' })

  const unfiltered = await testApp.app.inject({ method: 'GET', url: '/' })
  assert.match(unfiltered.body, /<option value="Oil on canvas"/)
  assert.match(unfiltered.body, /<option value="Stoneware"/)

  const filtered = await testApp.app.inject({
    method: 'GET',
    url: `/?medium=${encodeURIComponent('Oil on canvas')}`,
  })
  assert.match(filtered.body, /Harbour at dusk/)
  assert.doesNotMatch(filtered.body, /Kiln study/)
})

test('a grid wider than one page carries the visitor to the next one', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  for (let number = 1; number <= 13; number += 1) {
    await listArtwork(testApp, { sellerId: seller.id, title: `Study number ${number}` })
  }

  const first = await testApp.app.inject({ method: 'GET', url: '/' })
  assert.match(first.body, /Page 1 of 2/)
  assert.match(first.body, /page=2/)
  assert.doesNotMatch(first.body, /Study number 1</)

  const second = await testApp.app.inject({ method: 'GET', url: '/?page=2' })
  assert.match(second.body, /Page 2 of 2/)
  assert.match(second.body, /Study number 1</)
  assert.doesNotMatch(second.body, /Study number 13/)
})

test('a page past the end lands on the last page that exists', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  await listArtwork(testApp, { sellerId: seller.id, title: 'Harbour at dusk' })

  const response = await testApp.app.inject({ method: 'GET', url: '/?page=99' })

  assert.match(response.body, /Page 1 of 1/)
  assert.match(response.body, /Harbour at dusk/)
})
