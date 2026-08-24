/**
 * Who a request's log lines name. A browser may hold all three identity
 * cookies at once — the demo needs that — so the path decides which of them the
 * request is being made as, and any other identity it carries stands in when
 * the side being visited has none.
 */
import type { ActorType } from '../auth/actor-type.ts'
import type { ActorId } from '../ids/entity-ids.ts'

/** `system` is a CLI run or a background job: nobody asked for it. */
export type LogActorType = ActorType | 'system'

export type LogActor = { actorType: ActorType; actorId: ActorId }

/** The identity each side of the marketplace claims for one request. */
export type RequestIdentities = Readonly<Record<ActorType, ActorId | null>>

const PORTAL_PREFIXES = [
  { prefix: '/admin', actorType: 'admin' },
  { prefix: '/seller', actorType: 'seller' },
] as const satisfies readonly { prefix: string; actorType: ActorType }[]

/** Whichever identity to fall back on, most privileged first. */
const FALLBACK_ORDER = ['admin', 'seller', 'customer'] as const satisfies readonly ActorType[]

/** The side of the marketplace a path is served by; everything else is the storefront. */
export function siteActorType(path: string): ActorType {
  for (const portal of PORTAL_PREFIXES) {
    if (path === portal.prefix || path.startsWith(`${portal.prefix}/`)) return portal.actorType
  }

  return 'customer'
}

/**
 * The actor a request is made as: the identity belonging to the side being
 * visited, or the strongest other identity the browser carries. Null when the
 * browser has named nobody at all.
 */
export function requestActor(path: string, identities: RequestIdentities): LogActor | null {
  const site = siteActorType(path)
  const own = identities[site]
  if (own !== null) return { actorType: site, actorId: own }

  for (const actorType of FALLBACK_ORDER) {
    const actorId = identities[actorType]
    if (actorId !== null) return { actorType, actorId }
  }

  return null
}
