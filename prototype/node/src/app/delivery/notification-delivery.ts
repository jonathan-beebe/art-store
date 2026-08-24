import type { ActorId } from '../core/ids/entity-ids.ts'
import type { RecipientType } from '../core/notifications/recipient-type.ts'
import type { DeliveryContext } from './delivery-context.ts'

export type DeliverableNotification = {
  recipientType: RecipientType
  recipientId: ActorId
  subject: string
  body: string
  url: string | null
}

/**
 * How a notification reaches its recipient outside the in-app inbox. `deliver`
 * takes the caller's context so an implementation writes with the caller's
 * transaction; anything that leaves the process happens after the commit.
 */
export type NotificationDelivery = {
  deliver(context: DeliveryContext, notification: DeliverableNotification): Promise<void>
}
