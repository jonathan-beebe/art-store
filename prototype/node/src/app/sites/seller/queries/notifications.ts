import {
  parseNotificationRow,
  type ParsedNotification,
} from '../../../actions/notifications/notification-recipient.ts'
import type { NotificationId, SellerId } from '../../../core/ids/entity-ids.ts'
import type { AppDatabase } from '../../../db/database.ts'
import { toCount } from '../../../db/count.ts'
import type { ListPage } from '../../../core/paging/list-page.ts'

export async function notificationsForSeller(
  db: AppDatabase,
  sellerId: SellerId,
  page: Pick<ListPage, 'offset' | 'limit'>,
): Promise<readonly ParsedNotification[]> {
  const rows = await db
    .selectFrom('notifications')
    .selectAll()
    .where('sellerId', '=', sellerId)
    .orderBy('createdAt', 'desc')
    .orderBy('id', 'desc')
    .offset(page.offset)
    .limit(page.limit)
    .execute()

  return rows.map(parseNotificationRow)
}

export async function countNotificationsForSeller(db: AppDatabase, sellerId: SellerId): Promise<number> {
  const counted = await db
    .selectFrom('notifications')
    .select((eb) => eb.fn.countAll<string | number | bigint>().as('total'))
    .where('sellerId', '=', sellerId)
    .executeTakeFirstOrThrow()

  return toCount(counted.total)
}

export async function recentNotificationsForSeller(
  db: AppDatabase,
  sellerId: SellerId,
  limit: number,
): Promise<readonly ParsedNotification[]> {
  const rows = await db
    .selectFrom('notifications')
    .selectAll()
    .where('sellerId', '=', sellerId)
    .orderBy('createdAt', 'desc')
    .orderBy('id', 'desc')
    .limit(limit)
    .execute()

  return rows.map(parseNotificationRow)
}

export async function unreadNotificationCount(db: AppDatabase, sellerId: SellerId): Promise<number> {
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
  sellerId: SellerId,
  notificationId: NotificationId,
): Promise<ParsedNotification | null> {
  const notification = await db
    .selectFrom('notifications')
    .selectAll()
    .where('id', '=', notificationId)
    .where('sellerId', '=', sellerId)
    .executeTakeFirst()

  return notification === undefined ? null : parseNotificationRow(notification)
}
