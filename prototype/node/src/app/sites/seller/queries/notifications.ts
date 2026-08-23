import {
  parseNotificationRow,
  type ParsedNotification,
} from '../../../actions/notifications/notification-recipient.ts'
import type { AppDatabase } from '../../../db/database.ts'

export async function notificationsForSeller(
  db: AppDatabase,
  sellerId: number,
): Promise<readonly ParsedNotification[]> {
  const rows = await db
    .selectFrom('notifications')
    .selectAll()
    .where('sellerId', '=', sellerId)
    .orderBy('id', 'desc')
    .execute()

  return rows.map(parseNotificationRow)
}

export async function recentNotificationsForSeller(
  db: AppDatabase,
  sellerId: number,
  limit: number,
): Promise<readonly ParsedNotification[]> {
  const rows = await db
    .selectFrom('notifications')
    .selectAll()
    .where('sellerId', '=', sellerId)
    .orderBy('id', 'desc')
    .limit(limit)
    .execute()

  return rows.map(parseNotificationRow)
}

export async function unreadNotificationCount(db: AppDatabase, sellerId: number): Promise<number> {
  const row = await db
    .selectFrom('notifications')
    .select((eb) => eb.fn.countAll().as('count'))
    .where('sellerId', '=', sellerId)
    .where('readAt', 'is', null)
    .executeTakeFirstOrThrow()

  return Number(row.count)
}

export async function ownedNotification(
  db: AppDatabase,
  sellerId: number,
  notificationId: number,
): Promise<ParsedNotification | null> {
  const notification = await db
    .selectFrom('notifications')
    .selectAll()
    .where('id', '=', notificationId)
    .where('sellerId', '=', sellerId)
    .executeTakeFirst()

  return notification === undefined ? null : parseNotificationRow(notification)
}
