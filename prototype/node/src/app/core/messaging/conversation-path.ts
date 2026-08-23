import type { ActorType } from '../auth/actor-type.ts'

type ActorMessagingPaths = { conversation: string; inbox: string }

const MESSAGING_PATHS = {
  seller: { conversation: '/seller/messages/:id', inbox: '/seller/messages' },
  customer: { conversation: '/messages/:id', inbox: '/messages' },
  admin: { conversation: '/admin/messages/:id', inbox: '/admin/messages' },
} as const satisfies Record<ActorType, ActorMessagingPaths>

/** Where an actor of this type reads one conversation, on their own site. */
export function conversationPath(actorType: ActorType, conversationId: number): string {
  return MESSAGING_PATHS[actorType].conversation.replace(':id', String(conversationId))
}
