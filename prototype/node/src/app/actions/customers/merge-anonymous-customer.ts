import {
  planCustomerMerge,
  type CartLine,
  type CustomerMergePlan,
} from '../../core/customers/customer-merge-plan.ts'
import { planConversationFold, type ConversationFoldRow } from '../../core/customers/conversation-fold-plan.ts'
import { subjectKey } from '../../core/messaging/conversation-subject.ts'
import type { CartId, ConversationId, CustomerId, ListingId } from '../../core/ids/entity-ids.ts'
import type { AppDatabase } from '../../db/database.ts'
import { toTimestamp } from '../../db/timestamp.ts'
import { newId } from '../../ids.ts'
import type { ActionContext } from '../action-context.ts'
import { actionStory } from '../action-story.ts'
import { REPOINTED_CUSTOMER_TABLES } from './repointed-customer-tables.ts'

export type MergeSides = {
  anonymousCustomerId: CustomerId
  verifiedCustomerId: CustomerId
}

type Cart = { cartId: CartId | null; lines: readonly CartLine[] }

type CartSides = { anonymous: Cart; verified: Cart }

type FavoriteSides = { anonymous: readonly ListingId[]; verified: readonly ListingId[] }

const EMPTY_CART: Cart = { cartId: null, lines: [] }

/**
 * Folds an anonymous browsing history into the account that now owns the
 * address. The anonymous row survives the fold: the `customer_merges` row it
 * leaves behind is what lets a cookie on another device resolve forward instead
 * of starting the visitor over.
 */
export async function mergeAnonymousCustomer(
  context: ActionContext,
  sides: MergeSides,
): Promise<void> {
  await actionStory<CustomerMergePlan>(
    context,
    {
      event: 'customer.merge',
      will: {
        msg: 'folding the anonymous browsing history into the account',
        data: {
          anonymous_customer_id: sides.anonymousCustomerId,
          customer_id: sides.verifiedCustomerId,
        },
      },
      ended: (plan) => ({
        phase: 'did',
        msg: 'folded the anonymous browsing history into the account',
        data: {
          anonymous_customer_id: sides.anonymousCustomerId,
          customer_id: sides.verifiedCustomerId,
          cart_line_count: plan.cartLines.length,
          dropped_favorite_count: plan.favoritesToDrop.length,
        },
      }),
    },
    async ({ db: trx, clock }) => {
      const favorites = await readFavorites(trx, sides)
      const carts = await readCarts(trx, sides)
      const plan = planCustomerMerge({
        verifiedCartLines: carts.verified.lines,
        anonymousCartLines: carts.anonymous.lines,
        verifiedFavoriteListingIds: favorites.verified,
        anonymousFavoriteListingIds: favorites.anonymous,
        stockByListing: await readStock(trx, carts),
      })

      await repointOwnedRows(trx, sides)
      await foldConversations(trx, sides)
      await repointSentMessages(trx, sides)
      await applyFavorites(trx, sides, plan)
      await applyCart(trx, sides, carts, plan)

      await trx
        .insertInto('customerMerges')
        .values({
          id: newId('cmg', clock.now()),
          anonymousCustomerId: sides.anonymousCustomerId,
          customerId: sides.verifiedCustomerId,
          createdAt: toTimestamp(clock.now()),
        })
        .execute()

      return plan
    },
  )
}

async function repointOwnedRows(db: AppDatabase, sides: MergeSides): Promise<void> {
  for (const table of REPOINTED_CUSTOMER_TABLES) {
    await db
      .updateTable(table)
      .set({ customerId: sides.verifiedCustomerId })
      .where('customerId', '=', sides.anonymousCustomerId)
      .execute()
  }
}

/**
 * Moves the anonymous customer's threads onto the verified one, folding any
 * that land on a subject the verified customer already has a thread on —
 * `conversations.subject_key` is unique, so a blind repoint of a second
 * thread on the same subject would fail the insert this merge is not making,
 * but would still leave the verified customer with two threads on one
 * subject if the constraint were not there to catch it.
 */
async function foldConversations(db: AppDatabase, sides: MergeSides): Promise<void> {
  const anonymous = await conversationsOf(db, sides.anonymousCustomerId)
  if (anonymous.length === 0) return

  const verified = await conversationsOf(db, sides.verifiedCustomerId)

  for (const conversation of anonymous) {
    const plan = planConversationFold(conversation, sides.verifiedCustomerId, verified)

    if (plan.outcome === 'move') {
      await db
        .updateTable('conversations')
        .set({
          customerId: sides.verifiedCustomerId,
          subjectKey: subjectKey({ ...conversation, customerId: sides.verifiedCustomerId }),
        })
        .where('id', '=', plan.conversationId)
        .execute()
      continue
    }

    await absorbConversation(db, plan)
  }
}

async function conversationsOf(
  db: AppDatabase,
  customerId: CustomerId,
): Promise<readonly ConversationFoldRow[]> {
  return db
    .selectFrom('conversations')
    .select(['id', 'kind', 'sellerId', 'customerId', 'adminId', 'listingId', 'fulfillmentId'])
    .where('customerId', '=', customerId)
    .orderBy('createdAt')
    .orderBy('id')
    .execute()
}

/**
 * Takes over another thread's messages and drops it, preserving each
 * message's own `readAt` — the fold repoints `conversationId` only, so
 * whether the seller had already read a message the merge is moving is
 * unaffected. `lastMessageAt` is read back as the newest `sentAt` across the
 * standing thread's messages rather than carried over, the same as the
 * thread it absorbed would have shown it.
 */
async function absorbConversation(
  db: AppDatabase,
  fold: { movingId: ConversationId; standingId: ConversationId },
): Promise<void> {
  await db
    .updateTable('messages')
    .set({ conversationId: fold.standingId })
    .where('conversationId', '=', fold.movingId)
    .execute()

  await db.deleteFrom('conversations').where('id', '=', fold.movingId).execute()

  const newest = await db
    .selectFrom('messages')
    .select(({ fn }) => fn.max('sentAt').as('sentAt'))
    .where('conversationId', '=', fold.standingId)
    .executeTakeFirst()

  if (newest !== undefined && newest.sentAt !== null) {
    await db
      .updateTable('conversations')
      .set({ lastMessageAt: newest.sentAt })
      .where('id', '=', fold.standingId)
      .execute()
  }
}

/**
 * A message the anonymous customer sent reads as theirs by `senderId`, no
 * different from `orders.customerId` or any other owned row — `messages` just
 * has no foreign key to enforce it, since the same column holds a seller's or
 * an admin's id too depending on `senderType`.
 */
async function repointSentMessages(db: AppDatabase, sides: MergeSides): Promise<void> {
  await db
    .updateTable('messages')
    .set({ senderId: sides.verifiedCustomerId })
    .where('senderType', '=', 'customer')
    .where('senderId', '=', sides.anonymousCustomerId)
    .execute()
}

async function readFavorites(db: AppDatabase, sides: MergeSides): Promise<FavoriteSides> {
  return {
    anonymous: await readFavoriteListingIds(db, sides.anonymousCustomerId),
    verified: await readFavoriteListingIds(db, sides.verifiedCustomerId),
  }
}

async function readFavoriteListingIds(
  db: AppDatabase,
  customerId: CustomerId,
): Promise<readonly ListingId[]> {
  const rows = await db
    .selectFrom('favorites')
    .select('listingId')
    .where('customerId', '=', customerId)
    .orderBy('createdAt')
    .orderBy('id')
    .execute()

  return rows.map((row) => row.listingId)
}

async function applyFavorites(
  db: AppDatabase,
  sides: MergeSides,
  plan: CustomerMergePlan,
): Promise<void> {
  if (plan.favoritesToDrop.length > 0) {
    await db
      .deleteFrom('favorites')
      .where('customerId', '=', sides.anonymousCustomerId)
      .where('listingId', 'in', plan.favoritesToDrop)
      .execute()
  }

  await db
    .updateTable('favorites')
    .set({ customerId: sides.verifiedCustomerId })
    .where('customerId', '=', sides.anonymousCustomerId)
    .execute()
}

async function readCarts(db: AppDatabase, sides: MergeSides): Promise<CartSides> {
  return {
    anonymous: await readCart(db, sides.anonymousCustomerId),
    verified: await readCart(db, sides.verifiedCustomerId),
  }
}

async function readCart(db: AppDatabase, customerId: CustomerId): Promise<Cart> {
  const cart = await db
    .selectFrom('carts')
    .select('id')
    .where('customerId', '=', customerId)
    .orderBy('createdAt')
    .orderBy('id')
    .limit(1)
    .executeTakeFirst()

  if (cart === undefined) return EMPTY_CART

  const lines = await db
    .selectFrom('cartItems')
    .select(['listingId', 'quantity'])
    .where('cartId', '=', cart.id)
    .orderBy('createdAt')
    .orderBy('id')
    .execute()

  return { cartId: cart.id, lines }
}

async function readStock(
  db: AppDatabase,
  carts: CartSides,
): Promise<ReadonlyMap<ListingId, number>> {
  const listingIds = [...carts.verified.lines, ...carts.anonymous.lines].map(
    (line) => line.listingId,
  )

  if (listingIds.length === 0) return new Map()

  const rows = await db
    .selectFrom('listings')
    .select(['id', 'quantity'])
    .where('id', 'in', listingIds)
    .execute()

  return new Map(rows.map((row) => [row.id, row.quantity]))
}

/**
 * Moves the folded lines into one cart with updates only: the shapes of the
 * commerce tables belong to another concern, and an update needs to know none
 * of the columns it is not touching.
 */
async function applyCart(
  db: AppDatabase,
  sides: MergeSides,
  carts: CartSides,
  plan: CustomerMergePlan,
): Promise<void> {
  const sourceCartId = carts.anonymous.cartId
  if (sourceCartId === null) return

  const targetCartId = carts.verified.cartId ?? sourceCartId
  if (carts.verified.cartId === null) {
    await db
      .updateTable('carts')
      .set({ customerId: sides.verifiedCustomerId })
      .where('id', '=', sourceCartId)
      .execute()
  }

  const target = carts.verified.cartId === null ? carts.anonymous : carts.verified
  const inTarget = new Set(target.lines.map((line) => line.listingId))

  for (const line of plan.cartLines) {
    await writeCartLine(db, {
      targetCartId,
      sourceCartId,
      line,
      isInTarget: inTarget.has(line.listingId),
    })
  }

  await deleteCartItemsOutside(db, targetCartId, plan.cartLines)

  if (sourceCartId !== targetCartId) {
    await db.deleteFrom('cartItems').where('cartId', '=', sourceCartId).execute()
    await db.deleteFrom('carts').where('id', '=', sourceCartId).execute()
  }
}

async function writeCartLine(
  db: AppDatabase,
  move: { targetCartId: CartId; sourceCartId: CartId; line: CartLine; isInTarget: boolean },
): Promise<void> {
  const { targetCartId, sourceCartId, line, isInTarget } = move

  if (isInTarget) {
    await db
      .updateTable('cartItems')
      .set({ quantity: line.quantity })
      .where('cartId', '=', targetCartId)
      .where('listingId', '=', line.listingId)
      .execute()
    return
  }

  await db
    .updateTable('cartItems')
    .set({ cartId: targetCartId, quantity: line.quantity })
    .where('cartId', '=', sourceCartId)
    .where('listingId', '=', line.listingId)
    .execute()
}

async function deleteCartItemsOutside(
  db: AppDatabase,
  cartId: CartId,
  keptLines: readonly CartLine[],
): Promise<void> {
  const query = db.deleteFrom('cartItems').where('cartId', '=', cartId)

  if (keptLines.length === 0) {
    await query.execute()
    return
  }

  await query
    .where(
      'listingId',
      'not in',
      keptLines.map((line) => line.listingId),
    )
    .execute()
}
