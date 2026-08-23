import type { Clock } from '../clock.ts'
import type { AppDatabase } from '../db/database.ts'

/**
 * What a delivery writes with. `db` is the caller's handle, so a delivery that
 * queues a message queues it inside the transaction that caused it — the row
 * commits with the business change or rolls back with it.
 *
 * An `ActionContext` satisfies this, which is how an action hands its own
 * transaction to a delivery without knowing which delivery it has.
 */
export type DeliveryContext = {
  db: AppDatabase
  clock: Clock
}
