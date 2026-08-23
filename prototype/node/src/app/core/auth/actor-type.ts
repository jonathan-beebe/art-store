export const ACTOR_TYPES = ['seller', 'customer', 'admin'] as const

export type ActorType = (typeof ACTOR_TYPES)[number]

export function isActorType(value: string): value is ActorType {
  return ACTOR_TYPES.includes(value as ActorType)
}

export type ActorSite = {
  /** Where a verified link lands when it carries no destination of its own. */
  homePath: string
  /** Where a refused link and an unauthenticated request go. */
  loginPath: string
  /** Where signing out lands. */
  signedOutPath: string
}

export const ACTOR_SITES: Readonly<Record<ActorType, ActorSite>> = {
  seller: { homePath: '/seller', loginPath: '/seller/login', signedOutPath: '/seller/login' },
  // A customer signing out keeps browsing, so they land on the storefront and
  // the next request hands them a fresh anonymous identity.
  customer: { homePath: '/account', loginPath: '/login', signedOutPath: '/' },
  admin: { homePath: '/admin', loginPath: '/admin/login', signedOutPath: '/admin/login' },
}
