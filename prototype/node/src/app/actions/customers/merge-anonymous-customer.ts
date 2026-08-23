import { sql } from 'kysely'
import {
  planCustomerMerge,
  type CartLine,
  type CustomerMergePlan,
} from '../../core/customers/customer-merge-plan.ts'
import { REPOINTED_CUSTOMER_TABLES } from '../../core/customers/repointed-customer-tables.ts'
import type { AppDatabase } from '../../db/database.ts'
import { toTimestamp } from '../../db/timestamp.ts'
import type { ActionContext } from '../action-context.ts'
import { runInTransaction } from '../transaction.ts'
import {
  hasColumns,
  readMergedTableColumns,
  type MergedTableColumns,
} from './merged-table-columns.ts'

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
    const schema = await readMergedTableColumns(trx)
    const favorites = await readFavorites(trx, schema, sides)
    const carts = await readCarts(trx, schema, sides)
    const plan = planCustomerMerge({
      verifiedCartLines: carts.verified.lines,
      anonymousCartLines: carts.anonymous.lines,
      verifiedFavoriteListingIds: favorites.verified,
      anonymousFavoriteListingIds: favorites.anonymous,
      stockByListing: await readStock(trx, schema, carts),
    })

    await repointOwnedRows(trx, schema, sides)
    await applyFavorites(trx, schema, sides, favorites, plan)
    await applyCart(trx, schema, sides, carts, plan)

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

async function repointOwnedRows(
  db: AppDatabase,
  schema: MergedTableColumns,
  sides: MergeSides,
): Promise<void> {
  for (const { table, column } of REPOINTED_CUSTOMER_TABLES) {
    if (!hasColumns(schema, table, column)) continue

    await sql`
      update ${sql.table(table)} set ${sql.ref(column)} = ${sides.verifiedCustomerId}
      where ${sql.ref(column)} = ${sides.anonymousCustomerId}
    `.execute(db)
  }
}

function hasFavorites(schema: MergedTableColumns): boolean {
  return hasColumns(schema, 'favorites', 'customer_id', 'listing_id')
}

async function readFavorites(
  db: AppDatabase,
  schema: MergedTableColumns,
  sides: MergeSides,
): Promise<FavoriteSides> {
  if (!hasFavorites(schema)) return { anonymous: [], verified: [] }

  return {
    anonymous: await readFavoriteListingIds(db, sides.anonymousCustomerId),
    verified: await readFavoriteListingIds(db, sides.verifiedCustomerId),
  }
}

async function readFavoriteListingIds(db: AppDatabase, customerId: number): Promise<number[]> {
  const rows = await sql<{ listingId: number }>`
    select listing_id from favorites where customer_id = ${customerId} order by id
  `.execute(db)

  return rows.rows.map((row) => row.listingId)
}

async function applyFavorites(
  db: AppDatabase,
  schema: MergedTableColumns,
  sides: MergeSides,
  favorites: FavoriteSides,
  plan: CustomerMergePlan,
): Promise<void> {
  if (!hasFavorites(schema)) return

  const alreadyFavorited = new Set(favorites.verified)
  const moving = plan.favoriteListingIds.filter((listingId) => !alreadyFavorited.has(listingId))

  await deleteFavoritesOutside(db, sides.anonymousCustomerId, moving)

  await sql`
    update favorites set customer_id = ${sides.verifiedCustomerId}
    where customer_id = ${sides.anonymousCustomerId}
  `.execute(db)
}

async function deleteFavoritesOutside(
  db: AppDatabase,
  customerId: number,
  keptListingIds: readonly number[],
): Promise<void> {
  if (keptListingIds.length === 0) {
    await sql`delete from favorites where customer_id = ${customerId}`.execute(db)
    return
  }

  await sql`
    delete from favorites
    where customer_id = ${customerId} and listing_id not in (${sql.join(keptListingIds)})
  `.execute(db)
}

function hasCarts(schema: MergedTableColumns): boolean {
  return (
    hasColumns(schema, 'carts', 'id', 'customer_id') &&
    hasColumns(schema, 'cart_items', 'cart_id', 'listing_id', 'quantity')
  )
}

async function readCarts(
  db: AppDatabase,
  schema: MergedTableColumns,
  sides: MergeSides,
): Promise<CartSides> {
  if (!hasCarts(schema)) return { anonymous: EMPTY_CART, verified: EMPTY_CART }

  return {
    anonymous: await readCart(db, sides.anonymousCustomerId),
    verified: await readCart(db, sides.verifiedCustomerId),
  }
}

async function readCart(db: AppDatabase, customerId: number): Promise<Cart> {
  const carts = await sql<{ id: number }>`
    select id from carts where customer_id = ${customerId} order by id limit 1
  `.execute(db)

  const cartId = carts.rows[0]?.id ?? null
  if (cartId === null) return EMPTY_CART

  const items = await sql<CartLine>`
    select listing_id, quantity from cart_items where cart_id = ${cartId} order by id
  `.execute(db)

  return { cartId, lines: items.rows }
}

async function readStock(
  db: AppDatabase,
  schema: MergedTableColumns,
  carts: CartSides,
): Promise<ReadonlyMap<number, number>> {
  const listingIds = [...carts.verified.lines, ...carts.anonymous.lines].map(
    (line) => line.listingId,
  )

  if (listingIds.length === 0 || !hasColumns(schema, 'listings', 'id', 'quantity')) return new Map()

  const rows = await sql<{ id: number; quantity: number }>`
    select id, quantity from listings where id in (${sql.join(listingIds)})
  `.execute(db)

  return new Map(rows.rows.map((row) => [row.id, row.quantity]))
}

/**
 * Moves the folded lines into one cart with updates only: the shapes of the
 * commerce tables belong to another concern, and an update needs to know none
 * of the columns it is not touching.
 */
async function applyCart(
  db: AppDatabase,
  schema: MergedTableColumns,
  sides: MergeSides,
  carts: CartSides,
  plan: CustomerMergePlan,
): Promise<void> {
  const sourceCartId = carts.anonymous.cartId
  if (!hasCarts(schema) || sourceCartId === null) return

  const targetCartId = carts.verified.cartId ?? sourceCartId
  if (carts.verified.cartId === null) {
    await sql`
      update carts set customer_id = ${sides.verifiedCustomerId} where id = ${sourceCartId}
    `.execute(db)
  }

  const target = carts.verified.cartId === null ? carts.anonymous : carts.verified
  const inTarget = new Set(target.lines.map((line) => line.listingId))

  for (const line of plan.cartLines) {
    await writeCartLine(db, { targetCartId, sourceCartId, line, isInTarget: inTarget.has(line.listingId) })
  }

  await deleteCartItemsOutside(db, targetCartId, plan.cartLines)

  if (sourceCartId !== targetCartId) {
    await sql`delete from cart_items where cart_id = ${sourceCartId}`.execute(db)
    await sql`delete from carts where id = ${sourceCartId}`.execute(db)
  }
}

async function writeCartLine(
  db: AppDatabase,
  move: { targetCartId: number; sourceCartId: number; line: CartLine; isInTarget: boolean },
): Promise<void> {
  const { targetCartId, sourceCartId, line, isInTarget } = move

  if (isInTarget) {
    await sql`
      update cart_items set quantity = ${line.quantity}
      where cart_id = ${targetCartId} and listing_id = ${line.listingId}
    `.execute(db)
    return
  }

  await sql`
    update cart_items set cart_id = ${targetCartId}, quantity = ${line.quantity}
    where cart_id = ${sourceCartId} and listing_id = ${line.listingId}
  `.execute(db)
}

async function deleteCartItemsOutside(
  db: AppDatabase,
  cartId: number,
  keptLines: readonly CartLine[],
): Promise<void> {
  if (keptLines.length === 0) {
    await sql`delete from cart_items where cart_id = ${cartId}`.execute(db)
    return
  }

  const keptListingIds = keptLines.map((line) => line.listingId)

  await sql`
    delete from cart_items
    where cart_id = ${cartId} and listing_id not in (${sql.join(keptListingIds)})
  `.execute(db)
}
