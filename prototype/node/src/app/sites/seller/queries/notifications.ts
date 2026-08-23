import type { AppDatabase } from '../../../db/database.ts'
import type { Notification } from '../../../db/commerce-schema.ts'

export async function notificationsForSeller(db: AppDatabase, sellerId: number): Promise<readonly Notification[]> {
  return db.selectFrom('notifications').selectAll().where('sellerId', '=', sellerId).orderBy('id', 'desc').execute()
}

export async function recentNotificationsForSeller(
  db: AppDatabase,
  sellerId: number,
  limit: number,
): Promise<readonly Notification[]> {
  return db
    .selectFrom('notifications')
    .selectAll()
    .where('sellerId', '=', sellerId)
    .orderBy('id', 'desc')
    .limit(limit)
    .execute()
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
): Promise<Notification | null> {
  const notification = await db
    .selectFrom('notifications')
    .selectAll()
    .where('id', '=', notificationId)
    .where('sellerId', '=', sellerId)
    .executeTakeFirst()

  return notification ?? null
}
