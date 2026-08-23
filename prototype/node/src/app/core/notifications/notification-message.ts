import { formatCents, type Cents } from '../money.ts'

export type NotificationMessage = { subject: string; body: string; url: string | null }

export function itemSoldMessage(orderId: number, netCents: Cents, url?: string): NotificationMessage {
  return {
    subject: 'Item sold',
    body: `Order #${orderId} is paid. ${formatCents(netCents)} is held until the customer confirms delivery.`,
    url: url ?? null,
  }
}

export function orderShippedMessage(
  orderId: number,
  carrier: string,
  trackingNumber: string,
  url?: string,
): NotificationMessage {
  return {
    subject: 'Order shipped',
    body: `Order #${orderId} shipped with ${carrier}. Tracking number ${trackingNumber}.`,
    url: url ?? null,
  }
}

export function newMessageMessage(conversationSubject: string, url?: string): NotificationMessage {
  return {
    subject: 'New message',
    body: `You have a new message about ${conversationSubject}.`,
    url: url ?? null,
  }
}
