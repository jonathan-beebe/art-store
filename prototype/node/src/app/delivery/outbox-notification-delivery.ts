import type { ActorId } from '../core/ids/entity-ids.ts'
import type { RecipientType } from '../core/notifications/recipient-type.ts'
import type { AppDatabase } from '../db/database.ts'
import type { DeliveryContext } from './delivery-context.ts'
import type { DeliverableNotification, NotificationDelivery } from './notification-delivery.ts'
import { enqueueOutboxMessage } from './outbox-message.ts'

/** The table each kind of recipient keeps its address in. */
const RECIPIENT_TABLES = {
  seller: 'sellers',
  customer: 'customers',
  admin: 'admins',
} as const satisfies Record<RecipientType, string>

/**
 * Queues a notification for the outbox in the transaction that raised it, so a
 * rolled-back sale leaves nothing to send. A recipient who has given no address
 * — an anonymous customer — is reachable only through the in-app inbox, and
 * gets no row.
 */
export const outboxNotificationDelivery: NotificationDelivery = {
  async deliver(context: DeliveryContext, notification: DeliverableNotification): Promise<void> {
    const recipient = await recipientAddress(
      context.db,
      notification.recipientType,
      notification.recipientId,
    )
    if (recipient === null) return

    await enqueueOutboxMessage(context, {
      recipient,
      message: {
        subject: notification.subject,
        body: notification.body,
        url: notification.url,
      },
    })
  },
}

/** The address on the recipient's own row, or null where there is none. */
async function recipientAddress(
  db: AppDatabase,
  recipientType: RecipientType,
  recipientId: ActorId,
): Promise<string | null> {
  const row = await db
    .selectFrom(RECIPIENT_TABLES[recipientType])
    .select('email')
    .where('id', '=', recipientId)
    .executeTakeFirst()

  return row?.email ?? null
}
