import type { Clock } from '../clock.ts'
import type { AppDatabase } from '../db/database.ts'
import type { NotificationDelivery } from '../delivery/notification-delivery.ts'

/**
 * Everything a commerce action needs from the world. `db` is a Kysely handle
 * that may already be a transaction, which is what lets one action call
 * another and land in the same unit of work.
 *
 * `notificationDelivery` overrides where a notification goes beyond the in-app
 * inbox; left out, `notify` queues it for the outbox.
 */
export type ActionContext = {
  db: AppDatabase
  clock: Clock
  notificationDelivery?: NotificationDelivery
}
