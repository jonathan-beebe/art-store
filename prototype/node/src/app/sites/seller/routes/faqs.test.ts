import { test } from 'node:test'
import assert from 'node:assert/strict'
import { openConversation } from '../../../actions/messaging/open-conversation.ts'
import { postMessage } from '../../../actions/messaging/post-message.ts'
import type { ListingId, SellerId } from '../../../core/ids/entity-ids.ts'
import { mustSucceed } from '../../../core/refusal.ts'
import type { Message } from '../../../db/commerce-schema.ts'
import { buildTestApp, signInAsCustomer, signInAsSeller, type TestApp } from '../../../test/build-test-app.ts'
import { createForSaleListing } from '../test-fixtures.ts'

async function askAndAnswer(testApp: TestApp, sellerId: SellerId, listingId: ListingId): Promise<Message> {
  const { db, clock } = testApp
  const buyer = await signInAsCustomer(testApp, 'buyer@example.com')
  const conversation = await openConversation(
    { db, clock },
    { kind: 'listing_question', sellerId, customerId: buyer.id, listingId },
  )
  mustSucceed(
    await postMessage(
      { db, clock },
      { conversationId: conversation.id, sender: { type: 'customer', id: buyer.id }, body: 'Is this framed?' },
    ),
  )

  return mustSucceed(
    await postMessage(
      { db, clock },
      { conversationId: conversation.id, sender: { type: 'seller', id: sellerId }, body: 'Yes, in oak.' },
    ),
  ).message
}

test('the FAQ page lists published entries with an edit form and a blank publish form', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const listing = await createForSaleListing(testApp, seller.id, { title: 'Harbour at Dusk' })
  const answer = await askAndAnswer(testApp, seller.id, listing.id)
  await testApp.app.inject({
    method: 'POST',
    url: `/seller/listings/${listing.id}/faqs`,
    cookies: seller.cookies,
    payload: { question: 'Is this framed?', answer: 'Yes, in oak.', source_message_id: String(answer.id) },
  })

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/seller/listings/${listing.id}/faqs`,
    cookies: seller.cookies,
  })

  assert.equal(response.statusCode, 200)
  assert.match(response.body, /Harbour at Dusk/)
  assert.match(response.body, /data-faq="faq_[0-9A-HJKMNP-TV-Z]{26}"/)
  assert.match(response.body, new RegExp(`action="/seller/listings/${listing.id}/faqs"`))
})

test('a FAQ page for another seller\'s listing is not found', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const rival = await signInAsSeller(testApp, 'rival@example.com')
  const rivalListing = await createForSaleListing(testApp, rival.id)

  const response = await testApp.app.inject({
    method: 'GET',
    url: `/seller/listings/${rivalListing.id}/faqs`,
    cookies: seller.cookies,
  })

  assert.equal(response.statusCode, 404)
})

test('a non-numeric listing id is not found on the FAQ page', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)

  const response = await testApp.app.inject({
    method: 'GET',
    url: '/seller/listings/abc/faqs',
    cookies: seller.cookies,
  })

  assert.equal(response.statusCode, 404)
})

test('a non-numeric listing id is not found', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)

  const response = await testApp.app.inject({
    method: 'POST',
    url: '/seller/listings/abc/faqs',
    cookies: seller.cookies,
    payload: { question: 'Is this framed?', answer: 'Yes, in oak.' },
  })

  assert.equal(response.statusCode, 404)
})

test('a non-numeric FAQ id is not found on update', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const listing = await createForSaleListing(testApp, seller.id)

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/seller/listings/${listing.id}/faqs/abc`,
    cookies: seller.cookies,
    payload: { question: 'Is it framed?', answer: 'Yes, in oiled oak.' },
  })

  assert.equal(response.statusCode, 404)
})

test('a non-numeric FAQ id is not found on unpublish', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const listing = await createForSaleListing(testApp, seller.id)

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/seller/listings/${listing.id}/faqs/abc/unpublish`,
    cookies: seller.cookies,
  })

  assert.equal(response.statusCode, 404)
})

test('publishing against another seller\'s listing is not found', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const rival = await signInAsSeller(testApp, 'rival@example.com')
  const rivalListing = await createForSaleListing(testApp, rival.id)

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/seller/listings/${rivalListing.id}/faqs`,
    cookies: seller.cookies,
    payload: { question: 'Is this framed?', answer: 'Yes, in oak.' },
  })

  assert.equal(response.statusCode, 404)
  const faqs = await testApp.db.selectFrom('listingFaqs').selectAll().where('listingId', '=', rivalListing.id).execute()
  assert.equal(faqs.length, 0)
})

test('publishing an FAQ from a listing-question thread adds it to the listing', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const listing = await createForSaleListing(testApp, seller.id)
  const answer = await askAndAnswer(testApp, seller.id, listing.id)

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/seller/listings/${listing.id}/faqs`,
    cookies: seller.cookies,
    payload: { question: 'Is this framed?', answer: 'Yes, in oak.', source_message_id: String(answer.id) },
  })

  assert.equal(response.statusCode, 302)
  assert.equal(response.headers.location, `/seller/listings/${listing.id}/faqs`)

  const faq = await testApp.db
    .selectFrom('listingFaqs')
    .selectAll()
    .where('listingId', '=', listing.id)
    .executeTakeFirstOrThrow()
  assert.equal(faq.question, 'Is this framed?')
  assert.equal(faq.answer, 'Yes, in oak.')
  assert.equal(faq.sourceMessageId, answer.id)
})

test('publishing with a blank answer re-renders the publish form with a field error beside it, and publishes nothing', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const listing = await createForSaleListing(testApp, seller.id)

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/seller/listings/${listing.id}/faqs`,
    cookies: seller.cookies,
    payload: { question: 'Is this framed?', answer: '   ' },
  })

  assert.equal(response.statusCode, 422)
  assert.match(response.body, /data-field-error="new-faq-answer"[^>]*>Enter the answer\./)
  assert.match(response.body, /id="new-faq-question"[^>]*>Is this framed\?/)
  const faqs = await testApp.db.selectFrom('listingFaqs').selectAll().where('listingId', '=', listing.id).execute()
  assert.equal(faqs.length, 0)
})

test('a bodiless publish re-renders the publish form with what is missing beside each field, instead of failing', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const listing = await createForSaleListing(testApp, seller.id)

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/seller/listings/${listing.id}/faqs`,
    cookies: seller.cookies,
  })

  assert.equal(response.statusCode, 422)
  assert.match(response.body, /data-field-error="new-faq-question"[^>]*>Enter the question\./)
  assert.match(response.body, /data-field-error="new-faq-answer"[^>]*>Enter the answer\./)
  const faqs = await testApp.db.selectFrom('listingFaqs').selectAll().where('listingId', '=', listing.id).execute()
  assert.equal(faqs.length, 0)
})

test('editing a published FAQ updates its wording', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const listing = await createForSaleListing(testApp, seller.id)
  await testApp.app.inject({
    method: 'POST',
    url: `/seller/listings/${listing.id}/faqs`,
    cookies: seller.cookies,
    payload: { question: 'Is this framed?', answer: 'Yes, in oak.' },
  })
  const faq = await testApp.db
    .selectFrom('listingFaqs')
    .selectAll()
    .where('listingId', '=', listing.id)
    .executeTakeFirstOrThrow()

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/seller/listings/${listing.id}/faqs/${faq.id}`,
    cookies: seller.cookies,
    payload: { question: 'Is it framed?', answer: 'Yes, in oiled oak.' },
  })

  assert.equal(response.statusCode, 302)
  assert.equal(response.headers.location, `/seller/listings/${listing.id}/faqs`)
  const updated = await testApp.db
    .selectFrom('listingFaqs')
    .selectAll()
    .where('id', '=', faq.id)
    .executeTakeFirstOrThrow()
  assert.equal(updated.question, 'Is it framed?')
  assert.equal(updated.answer, 'Yes, in oiled oak.')
})

test('updating with a blank answer re-renders that row with a field error and leaves the FAQ unchanged', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const listing = await createForSaleListing(testApp, seller.id)
  await testApp.app.inject({
    method: 'POST',
    url: `/seller/listings/${listing.id}/faqs`,
    cookies: seller.cookies,
    payload: { question: 'Is this framed?', answer: 'Yes, in oak.' },
  })
  const faq = await testApp.db
    .selectFrom('listingFaqs')
    .selectAll()
    .where('listingId', '=', listing.id)
    .executeTakeFirstOrThrow()

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/seller/listings/${listing.id}/faqs/${faq.id}`,
    cookies: seller.cookies,
    payload: { question: 'Is this framed?', answer: '   ' },
  })

  assert.equal(response.statusCode, 422)
  assert.match(response.body, new RegExp(`data-field-error="faq-${faq.id}-answer"[^>]*>Enter the answer\\.`))
  const unchanged = await testApp.db
    .selectFrom('listingFaqs')
    .selectAll()
    .where('id', '=', faq.id)
    .executeTakeFirstOrThrow()
  assert.equal(unchanged.answer, 'Yes, in oak.')
})

test('unpublishing removes the FAQ', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const listing = await createForSaleListing(testApp, seller.id)
  await testApp.app.inject({
    method: 'POST',
    url: `/seller/listings/${listing.id}/faqs`,
    cookies: seller.cookies,
    payload: { question: 'Is this framed?', answer: 'Yes, in oak.' },
  })
  const faq = await testApp.db
    .selectFrom('listingFaqs')
    .selectAll()
    .where('listingId', '=', listing.id)
    .executeTakeFirstOrThrow()

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/seller/listings/${listing.id}/faqs/${faq.id}/unpublish`,
    cookies: seller.cookies,
  })

  assert.equal(response.statusCode, 302)
  assert.equal(response.headers.location, `/seller/listings/${listing.id}/faqs`)
  const remaining = await testApp.db.selectFrom('listingFaqs').selectAll().where('listingId', '=', listing.id).execute()
  assert.equal(remaining.length, 0)
})

test('a FAQ id on a different listing is not found on update and unpublish', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const listingA = await createForSaleListing(testApp, seller.id, { title: 'A' })
  const listingB = await createForSaleListing(testApp, seller.id, { title: 'B' })
  await testApp.app.inject({
    method: 'POST',
    url: `/seller/listings/${listingA.id}/faqs`,
    cookies: seller.cookies,
    payload: { question: 'Is this framed?', answer: 'Yes, in oak.' },
  })
  const faq = await testApp.db
    .selectFrom('listingFaqs')
    .selectAll()
    .where('listingId', '=', listingA.id)
    .executeTakeFirstOrThrow()

  const update = await testApp.app.inject({
    method: 'POST',
    url: `/seller/listings/${listingB.id}/faqs/${faq.id}`,
    cookies: seller.cookies,
    payload: { question: 'Different?', answer: 'Different.' },
  })
  assert.equal(update.statusCode, 404)

  const unpublish = await testApp.app.inject({
    method: 'POST',
    url: `/seller/listings/${listingB.id}/faqs/${faq.id}/unpublish`,
    cookies: seller.cookies,
  })
  assert.equal(unpublish.statusCode, 404)

  const unchanged = await testApp.db.selectFrom('listingFaqs').selectAll().where('id', '=', faq.id).executeTakeFirstOrThrow()
  assert.equal(unchanged.question, 'Is this framed?')
})

test('update and unpublish are not found against another seller\'s listing', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const rival = await signInAsSeller(testApp, 'rival@example.com')
  const rivalListing = await createForSaleListing(testApp, rival.id)
  await testApp.app.inject({
    method: 'POST',
    url: `/seller/listings/${rivalListing.id}/faqs`,
    cookies: rival.cookies,
    payload: { question: 'Is this framed?', answer: 'Yes, in oak.' },
  })
  const faq = await testApp.db
    .selectFrom('listingFaqs')
    .selectAll()
    .where('listingId', '=', rivalListing.id)
    .executeTakeFirstOrThrow()

  const update = await testApp.app.inject({
    method: 'POST',
    url: `/seller/listings/${rivalListing.id}/faqs/${faq.id}`,
    cookies: seller.cookies,
    payload: { question: 'Different?', answer: 'Different.' },
  })
  assert.equal(update.statusCode, 404)

  const unpublish = await testApp.app.inject({
    method: 'POST',
    url: `/seller/listings/${rivalListing.id}/faqs/${faq.id}/unpublish`,
    cookies: seller.cookies,
  })
  assert.equal(unpublish.statusCode, 404)

  const unchanged = await testApp.db.selectFrom('listingFaqs').selectAll().where('id', '=', faq.id).executeTakeFirstOrThrow()
  assert.equal(unchanged.question, 'Is this framed?')
})

test('publishing the same message twice is refused, and the thread still shows it published once', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const listing = await createForSaleListing(testApp, seller.id)
  const answer = await askAndAnswer(testApp, seller.id, listing.id)
  const conversation = await testApp.db
    .selectFrom('conversations')
    .selectAll()
    .where('id', '=', answer.conversationId)
    .executeTakeFirstOrThrow()

  const first = await testApp.app.inject({
    method: 'POST',
    url: `/seller/listings/${listing.id}/faqs`,
    cookies: seller.cookies,
    payload: { question: 'Is this framed?', answer: 'Yes, in oak.', source_message_id: String(answer.id) },
  })
  assert.equal(first.statusCode, 302)

  const second = await testApp.app.inject({
    method: 'POST',
    url: `/seller/listings/${listing.id}/faqs`,
    cookies: seller.cookies,
    payload: { question: 'Is this framed?', answer: 'Answered again.', source_message_id: String(answer.id) },
  })
  assert.equal(second.statusCode, 422)
  assert.match(second.body, /data-form-error/)
  assert.match(second.body, /already published/)

  const faqs = await testApp.db.selectFrom('listingFaqs').selectAll().where('listingId', '=', listing.id).execute()
  assert.equal(faqs.length, 1)
  assert.equal(faqs[0]?.answer, 'Yes, in oak.')

  const thread = await testApp.app.inject({
    method: 'GET',
    url: `/seller/messages/${conversation.id}`,
    cookies: seller.cookies,
  })
  assert.match(thread.body, /Published to FAQ/)
  assert.match(thread.body, new RegExp(`/seller/listings/${listing.id}/faqs#faq-${faqs[0]?.id}`))
})

test('a blank "Publish as FAQ" submission from the message thread re-renders that thread, not the FAQ index', async (t) => {
  const testApp = await buildTestApp()
  t.after(testApp.close)
  const seller = await signInAsSeller(testApp)
  const listing = await createForSaleListing(testApp, seller.id)
  const answer = await askAndAnswer(testApp, seller.id, listing.id)
  const conversation = await testApp.db
    .selectFrom('conversations')
    .selectAll()
    .where('id', '=', answer.conversationId)
    .executeTakeFirstOrThrow()

  const response = await testApp.app.inject({
    method: 'POST',
    url: `/seller/listings/${listing.id}/faqs`,
    cookies: seller.cookies,
    payload: {
      question: 'Is this framed?',
      answer: '   ',
      source_message_id: String(answer.id),
      conversation_id: conversation.id,
      redirect_to: `/seller/messages/${conversation.id}`,
    },
  })

  assert.equal(response.statusCode, 422)
  assert.match(response.body, new RegExp(`action="/seller/messages/${conversation.id}"`))
  assert.match(response.body, /data-field-error="faq-answer"[^>]*>Enter the answer\./)
  assert.match(response.body, /id="faq-question"[^>]*>Is this framed\?/)
  const faqs = await testApp.db.selectFrom('listingFaqs').selectAll().where('listingId', '=', listing.id).execute()
  assert.equal(faqs.length, 0)
})
