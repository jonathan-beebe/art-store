import type { RecipientType } from '../../core/notifications/recipient-type.ts'
import type { Notification } from '../../db/commerce-schema.ts'

/** A notification with the inbox it belongs to named once, in one field. */
export type ParsedNotification = Omit<Notification, 'sellerId' | 'customerId' | 'adminId'> & {
  recipientType: RecipientType
  recipientId: number
}

/**
 * Reads the one recipient column the row carries. The table's check constraint
 * fills exactly one of the three, so a row naming nobody is a broken database
 * rather than a case a caller answers.
 */
export function parseNotificationRow(row: Notification): ParsedNotification {
  const { sellerId, customerId, adminId, ...notification } = row

  if (sellerId !== null) return { ...notification, recipientType: 'seller', recipientId: sellerId }
  if (customerId !== null) {
    return { ...notification, recipientType: 'customer', recipientId: customerId }
  }
  if (adminId !== null) return { ...notification, recipientType: 'admin', recipientId: adminId }

  throw new TypeError(`notification ${row.id} names no recipient`)
}
