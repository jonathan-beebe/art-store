import {
  parseNotificationRow,
  type ParsedNotification,
} from '../../../actions/notifications/notification-recipient.ts'
import type { AppDatabase } from '../../../db/database.ts'

/** A customer's own notifications, most recent first. */
export async function findCustomerNotifications(
  db: AppDatabase,
  customerId: number,
): Promise<readonly ParsedNotification[]> {
  const rows = await db
    .selectFrom('notifications')
    .selectAll()
    .where('customerId', '=', customerId)
    .orderBy('id', 'desc')
    .execute()

  return rows.map(parseNotificationRow)
}

/** One notification, read as its own recipient: another customer's comes back
 * null and the page that asked answers "not found". */
export async function findCustomerNotification(
  db: AppDatabase,
  input: { id: number; customerId: number },
): Promise<ParsedNotification | null> {
  const notification = await db
    .selectFrom('notifications')
    .selectAll()
    .where('id', '=', input.id)
    .where('customerId', '=', input.customerId)
    .executeTakeFirst()

  return notification === undefined ? null : parseNotificationRow(notification)
}
