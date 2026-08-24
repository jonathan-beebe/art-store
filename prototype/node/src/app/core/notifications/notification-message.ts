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

/** What the customer reads when the seller will not ship their half of an order. */
export function fulfillmentDeclinedMessage(
  orderId: OrderId,
  amountCents: Cents,
  reason: string,
  url?: string,
): NotificationMessage {
  return {
    subject: 'Order declined',
    body:
      `The seller declined their part of order ${orderId}: ${reason}. ` +
      `${formatCents(amountCents)} is refunded.`,
    url: url ?? null,
  }
}

/** What both sides read when the platform reverses a sale over their heads. */
export function refundIssuedMessage(
  orderId: OrderId,
  amountCents: Cents,
  reason: string,
  url?: string,
): NotificationMessage {
  return {
    subject: 'Order refunded',
    body: `Art Store refunded ${formatCents(amountCents)} on order ${orderId}: ${reason}.`,
    url: url ?? null,
  }
}

/** What the seller reads when an order they had a share of is cancelled for them. */
export function orderCancelledMessage(orderId: OrderId, reason: string, url?: string): NotificationMessage {
  return {
    subject: 'Order cancelled',
    body: `Order ${orderId} was cancelled: ${reason}.`,
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
