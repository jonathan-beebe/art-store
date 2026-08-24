import { MAGIC_LINK_LIFETIME_MINUTES } from '../auth/magic-link-status.ts'
import { formatCents, type Cents } from '../money.ts'
import type { OrderId } from '../ids/entity-ids.ts'

export type NotificationMessage = { subject: string; body: string; url: string | null }

/** A sign-in link as a message, for the deliveries that carry one out. */
export function signInLinkMessage(url: string): NotificationMessage {
  return {
    subject: 'Your Art Store sign-in link',
    body:
      `Open the link below to sign in. It expires in ${MAGIC_LINK_LIFETIME_MINUTES} minutes ` +
      'and works once.',
    url,
  }
}

export function itemSoldMessage(orderId: OrderId, netCents: Cents, url?: string): NotificationMessage {
  return {
    subject: 'Item sold',
    body: `Order ${orderId} is paid. ${formatCents(netCents)} is held until the customer confirms delivery.`,
    url: url ?? null,
  }
}

export function orderShippedMessage(
  orderId: OrderId,
  carrier: string,
  trackingNumber: string,
  url?: string,
): NotificationMessage {
  return {
    subject: 'Order shipped',
    body: `Order ${orderId} shipped with ${carrier}. Tracking number ${trackingNumber}.`,
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
