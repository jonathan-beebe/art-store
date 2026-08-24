/**
 * The database as Kysely sees it. `CamelCasePlugin` maps snake_case columns to
 * camelCase properties, so row types are camelCase here while the migrations
 * create snake_case columns (`price_cents` reads as `priceCents`).
 *
 * A migration and its row type land together: whoever creates a table adds its
 * type here and touches no other line.
 *
 * Every key is a text prefixed ULID the application mints, so `id` is a plain
 * required column rather than something the database generates, and a foreign
 * key carries the referenced table's own id type: a `ListingId` will not go
 * where an `OrderId` belongs.
 */
import type { ActorType } from '../core/auth/actor-type.ts'
import type {
  AdminId,
  CustomerId,
  CustomerMergeId,
  MagicLinkId,
  SellerId,
} from '../core/ids/entity-ids.ts'
import type { CommerceTables } from './commerce-schema.ts'
import type { Timestamp } from './timestamp.ts'

export type SellerTable = {
  id: SellerId
  email: string
  name: string | null
  shopName: string | null
  emailVerifiedAt: Timestamp | null
  createdAt: Timestamp
}

/** `email` is null until a link verifies one: every visitor gets a row on arrival. */
export type CustomerTable = {
  id: CustomerId
  email: string | null
  name: string | null
  emailVerifiedAt: Timestamp | null
  createdAt: Timestamp
}

export type AdminTable = {
  id: AdminId
  email: string
  name: string
  createdAt: Timestamp
}

export type MagicLinkTable = {
  id: MagicLinkId
  tokenDigest: string
  email: string
  actorType: ActorType
  redirectTo: string | null
  expiresAt: Timestamp
  consumedAt: Timestamp | null
  createdAt: Timestamp
}

/** Points a cookie still holding `anonymousCustomerId` forward to `customerId`. */
export type CustomerMergeTable = {
  id: CustomerMergeId
  anonymousCustomerId: CustomerId
  customerId: CustomerId
  createdAt: Timestamp
}

export type Database = CommerceTables & {
  sellers: SellerTable
  customers: CustomerTable
  admins: AdminTable
  magicLinks: MagicLinkTable
  customerMerges: CustomerMergeTable
}
