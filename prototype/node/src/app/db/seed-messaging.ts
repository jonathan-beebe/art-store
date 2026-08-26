import type { ActionContext } from '../actions/action-context.ts'
import type { MessagingActor } from '../actions/messaging/conversation-actor.ts'
import { markConversationRead } from '../actions/messaging/mark-conversation-read.ts'
import { openConversation } from '../actions/messaging/open-conversation.ts'
import { postedMessage, postMessage } from '../actions/messaging/post-message.ts'
import { publishedFaq, publishListingFaq } from '../actions/messaging/publish-listing-faq.ts'
import { fixedClock } from '../clock.ts'
import type {
  AdminId,
  ConversationId,
  FulfillmentId,
  ListingId,
  SellerId,
} from '../core/ids/entity-ids.ts'
import type { Message } from './commerce-schema.ts'
import type { AppDatabase } from './database.ts'
import { REMOVED_LISTING_TITLE } from './seed-catalog.ts'
import type { SeededHermione } from './seed-customers.ts'

const REMOVED_LISTING_SELLER_EMAIL = 'colin@example.com'
const FULFILLMENT_SELLER_EMAIL = 'dean@example.com'
const FAQ_LISTING_SELLER_EMAIL = 'sybill@example.com'
export const FAQ_LISTING_TITLE = 'Divination Tower Vase, Tall'

const ADMIN_SELLER_OPENED_AT = new Date('2026-07-03T10:00:00.000Z')
const ADMIN_SELLER_MESSAGE_2_AT = new Date('2026-07-03T15:30:00.000Z')
const ADMIN_SELLER_MESSAGE_3_AT = new Date('2026-07-04T09:15:00.000Z')

const ADMIN_SELLER_MESSAGE_1 = `Hi Colin, we took "${REMOVED_LISTING_TITLE}" down temporarily on July 2 after a buyer said the print did not match the listed edition size. Can you confirm the edition count on that plate?`
const ADMIN_SELLER_MESSAGE_2 =
  'It is a numbered edition of twelve, this print is proof 4/12. I can send over the edition documentation if that helps clear it.'
const ADMIN_SELLER_MESSAGE_3 =
  'Thanks, that matches the listing. Send the documentation over when you can and we will lift the removal this week.'

const ADMIN_CUSTOMER_OPENED_AT = new Date('2026-07-22T09:00:00.000Z')
const ADMIN_CUSTOMER_MESSAGE_2_AT = new Date('2026-07-22T09:35:00.000Z')
const ADMIN_CUSTOMER_MESSAGE_3_AT = new Date('2026-07-22T10:05:00.000Z')

const ADMIN_CUSTOMER_MESSAGE_1 = 'Hi Hermione, following up on your recent orders. Anything we can help with?'
const ADMIN_CUSTOMER_MESSAGE_2 =
  'Actually, yes, could I get an itemized receipt for my July orders? I keep complete records of everything.'
const ADMIN_CUSTOMER_MESSAGE_3 = 'Sure thing, I will have receipts for all three orders sent your way today.'

const FULFILLMENT_OPENED_AT = new Date('2026-07-09T09:00:00.000Z')
const FULFILLMENT_MESSAGE_2_AT = new Date('2026-07-09T14:00:00.000Z')
const FULFILLMENT_MESSAGE_3_AT = new Date('2026-07-09T16:30:00.000Z')

const FULFILLMENT_MESSAGE_1 =
  'Your Gryffindor Common Room, Late Morning shipped with Owl Post, tracking OWL-2263-1187-GB.'
const FULFILLMENT_MESSAGE_2 = 'Thanks! Any estimate on when it will arrive?'
const FULFILLMENT_MESSAGE_3 = 'Owl Post from London usually runs 4-6 business days; a painting takes two owls.'

const LISTING_QUESTION_OPENED_AT = new Date('2026-07-25T10:00:00.000Z')
const LISTING_QUESTION_ANSWERED_AT = new Date('2026-07-25T13:45:00.000Z')
const FAQ_PUBLISHED_AT = new Date('2026-07-25T13:50:00.000Z')

const LISTING_QUESTION_TEXT = 'Is this vase watertight, or is it best kept for dried arrangements?'
const LISTING_QUESTION_ANSWER_TEXT =
  'It is fired unglazed, so it is not watertight. Best for dried arrangements, or use a liner if you want fresh-cut flowers. The patterns are for reading, not for water.'

export type SeededMessaging = {
  conversationCount: number
  messageCount: number
  faqCount: number
}

type ConversationStep =
  | { kind: 'message'; sender: MessagingActor; body: string; at: Date }
  | { kind: 'read'; reader: MessagingActor; at: Date }

/**
 * One conversation of each kind — admin/seller, admin/customer, a
 * seller/customer fulfillment thread, and a customer's listing question — each
 * a short exchange posted through the real messaging actions over a fixed
 * clock, plus one published listing FAQ lifted from the listing-question
 * thread's answer.
 */
export async function seedMessaging(
  db: AppDatabase,
  {
    adminId,
    sellerIdsByEmail,
    listingIdsByTitle,
    hermione,
    fulfillmentId,
  }: {
    adminId: AdminId
    sellerIdsByEmail: Record<string, SellerId>
    listingIdsByTitle: Record<string, ListingId>
    hermione: SeededHermione
    fulfillmentId: FulfillmentId
  },
): Promise<SeededMessaging> {
  const admin: AdminActor = { type: 'admin', id: adminId }
  const customer: CustomerActor = { type: 'customer', id: hermione.id }

  const adminSellerMessages = await seedAdminSellerThread(db, {
    admin,
    seller: { type: 'seller', id: requireSellerId(sellerIdsByEmail, REMOVED_LISTING_SELLER_EMAIL) },
  })
  const adminCustomerMessages = await seedAdminCustomerThread(db, { admin, customer })
  const fulfillmentMessages = await seedFulfillmentThread(db, {
    seller: { type: 'seller', id: requireSellerId(sellerIdsByEmail, FULFILLMENT_SELLER_EMAIL) },
    customer,
    fulfillmentId,
  })
  const faqListingId = requireListingId(listingIdsByTitle, FAQ_LISTING_TITLE)
  const listingQuestionMessages = await seedListingQuestionThread(db, {
    seller: { type: 'seller', id: requireSellerId(sellerIdsByEmail, FAQ_LISTING_SELLER_EMAIL) },
    customer,
    listingId: faqListingId,
  })

  await publishFaq(db, faqListingId, listingQuestionMessages)

  const messageCount =
    adminSellerMessages.length +
    adminCustomerMessages.length +
    fulfillmentMessages.length +
    listingQuestionMessages.length

  return { conversationCount: 4, messageCount, faqCount: 1 }
}

/** One side of a seeded thread, narrowed so a seller's id cannot open an
 * admin's half of it. */
type AdminActor = Extract<MessagingActor, { type: 'admin' }>
type SellerActor = Extract<MessagingActor, { type: 'seller' }>
type CustomerActor = Extract<MessagingActor, { type: 'customer' }>

function actionContext(db: AppDatabase, at: Date): ActionContext {
  return { db, clock: fixedClock(at) }
}

async function runConversationSteps(
  db: AppDatabase,
  conversationId: ConversationId,
  steps: readonly ConversationStep[],
): Promise<readonly Message[]> {
  const messages: Message[] = []
  for (const step of steps) {
    const context = actionContext(db, step.at)
    if (step.kind === 'message') {
      messages.push(postedMessage(await postMessage(context, { conversationId, sender: step.sender, body: step.body })))
    } else {
      await markConversationRead(context, { conversationId, reader: step.reader })
    }
  }
  return messages
}

/** The admin reaches out about the temporary removal; the seller answers, and
 * the admin's closing reply is left unread so the seller portal shows a badge. */
async function seedAdminSellerThread(
  db: AppDatabase,
  { admin, seller }: { admin: AdminActor; seller: SellerActor },
): Promise<readonly Message[]> {
  const conversation = await openConversation(actionContext(db, ADMIN_SELLER_OPENED_AT), {
    kind: 'admin_seller',
    adminId: admin.id,
    sellerId: seller.id,
  })

  return runConversationSteps(db, conversation.id, [
    { kind: 'message', sender: admin, body: ADMIN_SELLER_MESSAGE_1, at: ADMIN_SELLER_OPENED_AT },
    { kind: 'message', sender: seller, body: ADMIN_SELLER_MESSAGE_2, at: ADMIN_SELLER_MESSAGE_2_AT },
    { kind: 'read', reader: admin, at: ADMIN_SELLER_MESSAGE_2_AT },
    { kind: 'read', reader: seller, at: ADMIN_SELLER_MESSAGE_2_AT },
    { kind: 'message', sender: admin, body: ADMIN_SELLER_MESSAGE_3, at: ADMIN_SELLER_MESSAGE_3_AT },
  ])
}

/** A support thread with Hermione; the admin's closing reply is left unread so
 * the storefront shows a badge. */
async function seedAdminCustomerThread(
  db: AppDatabase,
  { admin, customer }: { admin: AdminActor; customer: CustomerActor },
): Promise<readonly Message[]> {
  const conversation = await openConversation(actionContext(db, ADMIN_CUSTOMER_OPENED_AT), {
    kind: 'admin_customer',
    adminId: admin.id,
    customerId: customer.id,
  })

  return runConversationSteps(db, conversation.id, [
    { kind: 'message', sender: admin, body: ADMIN_CUSTOMER_MESSAGE_1, at: ADMIN_CUSTOMER_OPENED_AT },
    { kind: 'message', sender: customer, body: ADMIN_CUSTOMER_MESSAGE_2, at: ADMIN_CUSTOMER_MESSAGE_2_AT },
    { kind: 'read', reader: admin, at: ADMIN_CUSTOMER_MESSAGE_2_AT },
    { kind: 'read', reader: customer, at: ADMIN_CUSTOMER_MESSAGE_2_AT },
    { kind: 'message', sender: admin, body: ADMIN_CUSTOMER_MESSAGE_3, at: ADMIN_CUSTOMER_MESSAGE_3_AT },
  ])
}

/** Keyed to the fulfillment `seed-order-history.ts` ships for "Gryffindor
 * Common Room, Late Morning", so `FULFILLMENT_SELLER_EMAIL` must stay Dean
 * Thomas. Fully read: a demo thread with no badge to show. */
async function seedFulfillmentThread(
  db: AppDatabase,
  {
    seller,
    customer,
    fulfillmentId,
  }: { seller: SellerActor; customer: CustomerActor; fulfillmentId: FulfillmentId },
): Promise<readonly Message[]> {
  const conversation = await openConversation(actionContext(db, FULFILLMENT_OPENED_AT), {
    kind: 'fulfillment',
    sellerId: seller.id,
    customerId: customer.id,
    fulfillmentId,
  })

  const messages = await runConversationSteps(db, conversation.id, [
    { kind: 'message', sender: seller, body: FULFILLMENT_MESSAGE_1, at: FULFILLMENT_OPENED_AT },
    { kind: 'message', sender: customer, body: FULFILLMENT_MESSAGE_2, at: FULFILLMENT_MESSAGE_2_AT },
    { kind: 'message', sender: seller, body: FULFILLMENT_MESSAGE_3, at: FULFILLMENT_MESSAGE_3_AT },
  ])

  await runConversationSteps(db, conversation.id, [
    { kind: 'read', reader: seller, at: FULFILLMENT_MESSAGE_3_AT },
    { kind: 'read', reader: customer, at: FULFILLMENT_MESSAGE_3_AT },
  ])

  return messages
}

/** Hermione asks about a for_sale listing and the seller answers; fully read,
 * so the answer is ready to publish as an FAQ. */
async function seedListingQuestionThread(
  db: AppDatabase,
  {
    seller,
    customer,
    listingId,
  }: { seller: SellerActor; customer: CustomerActor; listingId: ListingId },
): Promise<readonly Message[]> {
  const conversation = await openConversation(actionContext(db, LISTING_QUESTION_OPENED_AT), {
    kind: 'listing_question',
    sellerId: seller.id,
    customerId: customer.id,
    listingId,
  })

  const messages = await runConversationSteps(db, conversation.id, [
    { kind: 'message', sender: customer, body: LISTING_QUESTION_TEXT, at: LISTING_QUESTION_OPENED_AT },
    { kind: 'message', sender: seller, body: LISTING_QUESTION_ANSWER_TEXT, at: LISTING_QUESTION_ANSWERED_AT },
  ])

  await runConversationSteps(db, conversation.id, [
    { kind: 'read', reader: seller, at: LISTING_QUESTION_ANSWERED_AT },
    { kind: 'read', reader: customer, at: LISTING_QUESTION_ANSWERED_AT },
  ])

  return messages
}

async function publishFaq(db: AppDatabase, listingId: ListingId, threadMessages: readonly Message[]): Promise<void> {
  const sellerAnswer = threadMessages[1]
  if (sellerAnswer === undefined) {
    throw new Error('seedMessaging: the listing-question thread has no seller answer to publish')
  }

  publishedFaq(
    await publishListingFaq(actionContext(db, FAQ_PUBLISHED_AT), {
      listingId,
      draft: { question: LISTING_QUESTION_TEXT, answer: LISTING_QUESTION_ANSWER_TEXT },
      sourceMessageId: sellerAnswer.id,
    }),
  )
}

function requireSellerId(sellerIdsByEmail: Record<string, SellerId>, email: string): SellerId {
  const sellerId = sellerIdsByEmail[email]
  if (sellerId === undefined) {
    throw new Error(`seedMessaging: no seeded seller for ${email}`)
  }
  return sellerId
}

function requireListingId(listingIdsByTitle: Record<string, ListingId>, title: string): ListingId {
  const listingId = listingIdsByTitle[title]
  if (listingId === undefined) {
    throw new Error(`seedMessaging: no seeded listing titled ${title}`)
  }
  return listingId
}
