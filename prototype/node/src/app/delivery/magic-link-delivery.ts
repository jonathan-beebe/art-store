import type { ActorType } from '../core/auth/actor-type.ts'
import type { Flash } from '../plugins/flash.ts'
import type { DeliveryContext } from './delivery-context.ts'
import { flashMagicLinkDelivery } from './flash-magic-link-delivery.ts'
import { outboxMagicLinkDelivery } from './outbox-magic-link-delivery.ts'

export type MagicLinkMessage = {
  email: string
  url: string
  actorType: ActorType
}

/**
 * How a sign-in link reaches the person who asked for it. `deliver` returns the
 * flash the route hands to the next page, which is empty for any transport that
 * carries the link out of the application, and takes the caller's context so a
 * queued message is written in the same transaction as the link itself.
 */
export type MagicLinkDelivery = {
  deliver(context: DeliveryContext, message: MagicLinkMessage): Promise<Flash>
}

export const MAGIC_LINK_DELIVERIES = ['flash', 'outbox'] as const

export type MagicLinkDeliveryName = (typeof MAGIC_LINK_DELIVERIES)[number]

const DELIVERIES = {
  flash: flashMagicLinkDelivery,
  outbox: outboxMagicLinkDelivery,
} satisfies Record<MagicLinkDeliveryName, MagicLinkDelivery>

export function selectMagicLinkDelivery(name: MagicLinkDeliveryName): MagicLinkDelivery {
  return DELIVERIES[name]
}
