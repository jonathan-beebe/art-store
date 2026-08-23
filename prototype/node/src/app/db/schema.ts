/**
 * The database as Kysely sees it. `CamelCasePlugin` maps snake_case columns to
 * camelCase properties, so row types are camelCase here while the migrations
 * create snake_case columns (`price_cents` reads as `priceCents`).
 *
 * A migration and its row type land together: whoever creates a table adds its
 * type here and touches no other line.
 */
export type Database = Record<never, never>
