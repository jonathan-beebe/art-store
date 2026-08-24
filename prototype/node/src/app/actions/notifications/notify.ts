import { newId } from '../../ids.ts'
import type { ActionContext } from '../action-context.ts'
import { runInTransaction } from '../transaction.ts'
import type { NotificationMessage } from '../../core/notifications/notification-message.ts'
import type { NotificationRecipient } from '../../core/notifications/recipient-type.ts'
import type { Notification } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'
import { outboxNotificationDelivery } from '../../delivery/outbox-notification-delivery.ts'

export type NotifyInput = NotificationRecipient & {
  message: NotificationMessage
}

/** The three recipient columns with the one this inbox names filled and the rest null. */
function recipientColumns(
  recipient: NotificationRecipient,
): Pick<Notification, 'sellerId' | 'customerId' | 'adminId'> {
  switch (recipient.recipientType) {
    case 'seller':
      return { sellerId: recipient.recipientId, customerId: null, adminId: null }
    case 'customer':
      return { sellerId: null, customerId: recipient.recipientId, adminId: null }
    case 'admin':
      return { sellerId: null, customerId: null, adminId: recipient.recipientId }
  }
}

/**
 * Files a message in one actor's inbox and hands it to the delivery that
 * carries it further. Both writes run in the caller's transaction, so a
 * business change that rolls back sends nothing — which is why the delivery
 * queues a row rather than reaching outside the process here.
 */
export async function notify(context: ActionContext, input: NotifyInput): Promise<Notification> {
  const delivery = context.notificationDelivery ?? outboxNotificationDelivery

  return runInTransaction(context, async (transacted) => {
    const { db, clock } = transacted
    const notification = await db
      .insertInto('notifications')
      .values({
        id: newId('ntf', clock.now()),
        ...recipientColumns(input),
        subject: input.message.subject,
        body: input.message.body,
        url: input.message.url,
        createdAt: toTimestamp(clock.now()),
      })
      .returningAll()
      .executeTakeFirstOrThrow()

    await delivery.deliver(transacted, {
      recipientType: input.recipientType,
      recipientId: input.recipientId,
      ...input.message,
    })

    return notification
  })
}
