import type { NotificationMessage } from '../core/notifications/notification-message.ts'
import { renderMailMessage } from '../core/notifications/mail-message.ts'
import type { OutboxMessage } from '../db/commerce-schema.ts'
import { fromTimestamp, toTimestamp } from '../db/timestamp.ts'
import type { DeliveryContext } from './delivery-context.ts'

/** The domain every message this prototype writes is addressed from. */
const MESSAGE_ID_DOMAIN = 'art-store.example'

export type OutboxMessageInput = {
  recipient: string
  message: NotificationMessage
}

/**
 * Queues one message for the drain to write out. The insert runs on the
 * caller's handle, so it belongs to whatever transaction the caller opened.
 */
export async function enqueueOutboxMessage(
  { db, clock }: DeliveryContext,
  { recipient, message }: OutboxMessageInput,
): Promise<void> {
  await db
    .insertInto('outboxMessages')
    .values({
      recipient,
      subject: message.subject,
      body: message.body,
      url: message.url,
      createdAt: toTimestamp(clock.now()),
      deliveredAt: null,
    })
    .execute()
}

/** One stored message as the RFC-5322 text a mailbox or an `.eml` file holds. */
export function renderOutboxMessage(row: OutboxMessage): string {
  return renderMailMessage({
    to: row.recipient,
    subject: row.subject,
    body: row.body,
    url: row.url,
    messageId: `outbox-${row.id}@${MESSAGE_ID_DOMAIN}`,
    date: fromTimestamp(row.createdAt),
  })
}
