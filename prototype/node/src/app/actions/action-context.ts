import type { Clock } from '../clock.ts'
import type { NotificationDelivery } from '../core/notifications/notification-delivery.ts'
import type { AppDatabase } from '../db/database.ts'

/**
 * Everything a commerce action needs from the world. `db` is a Kysely handle
 * that may already be a transaction, which is what lets one action call
 * another and land in the same unit of work.
 */
export type ActionContext = {
  db: AppDatabase
  clock: Clock
  notificationDelivery?: NotificationDelivery
}
