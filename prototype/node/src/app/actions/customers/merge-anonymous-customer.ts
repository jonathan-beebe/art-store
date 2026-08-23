import {
  planCustomerMerge,
  type CartLine,
  type CustomerMergePlan,
} from '../../core/customers/customer-merge-plan.ts'
import type { AppDatabase } from '../../db/database.ts'
import { toTimestamp } from '../../db/timestamp.ts'
import type { ActionContext } from '../action-context.ts'
import { runInTransaction } from '../transaction.ts'
import { REPOINTED_CUSTOMER_TABLES } from './repointed-customer-tables.ts'

export type MergeSides = {
  anonymousCustomerId: number
  verifiedCustomerId: number
}

type Cart = { cartId: number | null; lines: readonly CartLine[] }

type CartSides = { anonymous: Cart; verified: Cart }

type FavoriteSides = { anonymous: readonly number[]; verified: readonly number[] }

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
  await runInTransaction(context, async ({ db: trx, clock }) => {
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
    await applyFavorites(trx, sides, plan)
    await applyCart(trx, sides, carts, plan)

    await trx
      .insertInto('customerMerges')
      .values({
        anonymousCustomerId: sides.anonymousCustomerId,
        customerId: sides.verifiedCustomerId,
        createdAt: toTimestamp(clock.now()),
      })
      .execute()
  })
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

async function readFavorites(db: AppDatabase, sides: MergeSides): Promise<FavoriteSides> {
  return {
    anonymous: await readFavoriteListingIds(db, sides.anonymousCustomerId),
    verified: await readFavoriteListingIds(db, sides.verifiedCustomerId),
  }
}

async function readFavoriteListingIds(
  db: AppDatabase,
  customerId: number,
): Promise<readonly number[]> {
  const rows = await db
    .selectFrom('favorites')
    .select('listingId')
    .where('customerId', '=', customerId)
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

async function readCart(db: AppDatabase, customerId: number): Promise<Cart> {
  const cart = await db
    .selectFrom('carts')
    .select('id')
    .where('customerId', '=', customerId)
    .orderBy('id')
    .limit(1)
    .executeTakeFirst()

  if (cart === undefined) return EMPTY_CART

  const lines = await db
    .selectFrom('cartItems')
    .select(['listingId', 'quantity'])
    .where('cartId', '=', cart.id)
    .orderBy('id')
    .execute()

  return { cartId: cart.id, lines }
}

async function readStock(
  db: AppDatabase,
  carts: CartSides,
): Promise<ReadonlyMap<number, number>> {
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
  move: { targetCartId: number; sourceCartId: number; line: CartLine; isInTarget: boolean },
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
  cartId: number,
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
