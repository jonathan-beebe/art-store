import type { RecipientType } from './recipient-type.ts'

export type DeliverableNotification = {
  recipientType: RecipientType
  recipientId: number
  subject: string
  body: string
  url: string | null
}

export type NotificationDelivery = {
  deliver(notification: DeliverableNotification): Promise<void>
}
