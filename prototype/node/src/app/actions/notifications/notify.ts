import type { ActionContext } from '../action-context.ts'
import { runInTransaction } from '../transaction.ts'
import type { NotificationMessage } from '../../core/notifications/notification-message.ts'
import type { RecipientType } from '../../core/notifications/recipient-type.ts'
import type { Notification } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

export type NotifyInput = {
  recipientType: RecipientType
  recipientId: number
  message: NotificationMessage
}

const RECIPIENT_COLUMNS = {
  seller: 'sellerId',
  customer: 'customerId',
  admin: 'adminId',
} as const

/**
 * Files a message in one actor's inbox. Delivery beyond the inbox is a port, so
 * mail is a different implementation rather than a different call site.
 */
export async function notify(context: ActionContext, input: NotifyInput): Promise<Notification> {
  return runInTransaction(context, async ({ db, clock }) => {
    const notification = await db
      .insertInto('notifications')
      .values({
        sellerId: null,
        customerId: null,
        adminId: null,
        [RECIPIENT_COLUMNS[input.recipientType]]: input.recipientId,
        subject: input.message.subject,
        body: input.message.body,
        url: input.message.url,
        createdAt: toTimestamp(clock.now()),
      })
      .returningAll()
      .executeTakeFirstOrThrow()

    await context.notificationDelivery?.deliver({
      recipientType: input.recipientType,
      recipientId: input.recipientId,
      ...input.message,
    })

    return notification
  })
}
