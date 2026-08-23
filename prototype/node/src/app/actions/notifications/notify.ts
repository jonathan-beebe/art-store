import type { ActionContext } from '../action-context.ts'
import type { NotificationMessage } from '../../core/notifications/notification-message.ts'
import type { RecipientType } from '../../core/notifications/recipient-type.ts'
import type { Notification } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

export type NotifyInput = {
  recipientType: RecipientType
  recipientId: number
  message: NotificationMessage
}

/** The three recipient columns with the one this inbox names filled and the rest null. */
function recipientColumns(
  recipientType: RecipientType,
  recipientId: number,
): Pick<Notification, 'sellerId' | 'customerId' | 'adminId'> {
  switch (recipientType) {
    case 'seller':
      return { sellerId: recipientId, customerId: null, adminId: null }
    case 'customer':
      return { sellerId: null, customerId: recipientId, adminId: null }
    case 'admin':
      return { sellerId: null, customerId: null, adminId: recipientId }
  }
}

/**
 * Files a message in one actor's inbox. Delivery beyond the inbox is a port, so
 * mail is a different implementation rather than a different call site, and it
 * runs after the row is written rather than as part of writing it.
 */
export async function notify(
  { db, clock, notificationDelivery }: ActionContext,
  input: NotifyInput,
): Promise<Notification> {
  const notification = await db
    .insertInto('notifications')
    .values({
      ...recipientColumns(input.recipientType, input.recipientId),
      subject: input.message.subject,
      body: input.message.body,
      url: input.message.url,
      createdAt: toTimestamp(clock.now()),
    })
    .returningAll()
    .executeTakeFirstOrThrow()

  await notificationDelivery?.deliver({
    recipientType: input.recipientType,
    recipientId: input.recipientId,
    ...input.message,
  })

  return notification
}
