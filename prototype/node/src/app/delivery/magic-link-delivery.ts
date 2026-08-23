import type { ActorType } from '../core/auth/actor-type.ts'
import type { Flash } from '../plugins/flash.ts'
import { flashMagicLinkDelivery } from './flash-magic-link-delivery.ts'
import { mailMagicLinkDelivery } from './mail-magic-link-delivery.ts'

export type MagicLinkMessage = {
  email: string
  url: string
  actorType: ActorType
}

/**
 * How a sign-in link reaches the person who asked for it. `deliver` returns the
 * flash the route hands to the next page, which is empty for any transport that
 * carries the link out of the application.
 */
export type MagicLinkDelivery = {
  deliver(message: MagicLinkMessage): Flash
}

export const MAGIC_LINK_DELIVERIES = ['flash', 'mail'] as const

export type MagicLinkDeliveryName = (typeof MAGIC_LINK_DELIVERIES)[number]

const DELIVERIES: Readonly<Record<MagicLinkDeliveryName, MagicLinkDelivery>> = {
  flash: flashMagicLinkDelivery,
  mail: mailMagicLinkDelivery,
}

export function selectMagicLinkDelivery(name: MagicLinkDeliveryName): MagicLinkDelivery {
  return DELIVERIES[name]
}
