/**
 * The database as Kysely sees it. `CamelCasePlugin` maps snake_case columns to
 * camelCase properties, so row types are camelCase here while the migrations
 * create snake_case columns (`price_cents` reads as `priceCents`).
 *
 * A migration and its row type land together: whoever creates a table adds its
 * type here and touches no other line.
 */
import type { Generated } from 'kysely'
import type { ActorType } from '../core/auth/actor-type.ts'
import type { CommerceTables } from './commerce-schema.ts'
import type { Timestamp } from './timestamp.ts'

export type SellerTable = {
  id: Generated<number>
  email: string
  name: string | null
  shopName: string | null
  emailVerifiedAt: Timestamp | null
  createdAt: Timestamp
}

/** `email` is null until a link verifies one: every visitor gets a row on arrival. */
export type CustomerTable = {
  id: Generated<number>
  email: string | null
  name: string | null
  emailVerifiedAt: Timestamp | null
  createdAt: Timestamp
}

export type AdminTable = {
  id: Generated<number>
  email: string
  name: string
  createdAt: Timestamp
}

export type MagicLinkTable = {
  id: Generated<number>
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
  id: Generated<number>
  anonymousCustomerId: number
  customerId: number
  createdAt: Timestamp
}

export type Database = CommerceTables & {
  sellers: SellerTable
  customers: CustomerTable
  admins: AdminTable
  magicLinks: MagicLinkTable
  customerMerges: CustomerMergeTable
}
