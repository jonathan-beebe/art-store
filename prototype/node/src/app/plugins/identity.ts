import type { FastifyReply, FastifyRequest, preHandlerAsyncHookHandler } from 'fastify'
import type { Selectable } from 'kysely'
import { resolveCurrentCustomer } from '../actions/customers/resolve-current-customer.ts'
import { resolveCustomerFromCookie } from '../actions/customers/resolve-customer-from-cookie.ts'
import { ACTOR_SITES, type ActorType } from '../core/auth/actor-type.ts'
import { isVerifiedCustomer } from '../core/customers/customer-verification.ts'
import type { IdPrefix } from '../core/ids/entity-ids.ts'
import { parsePrefixedId, type PrefixedId } from '../core/ids/prefixed-id.ts'
import type { AdminTable, CustomerTable, SellerTable } from '../db/schema.ts'
import { rootPlugin } from './root-plugin.ts'

/**
 * One cookie per side of the marketplace, so a single browser can be a seller,
 * a customer, and an admin at once — the demo needs that.
 */
const IDENTITY_COOKIES = {
  seller: 'seller_id',
  customer: 'customer_id',
  admin: 'admin_id',
} as const satisfies Record<ActorType, string>

// A browsing history is worth more than a session, so the cookie outlives one.
const COOKIE_LIFETIME_SECONDS = 365 * 24 * 60 * 60

/** The table each side of the marketplace keeps its actors in. */
const ACTOR_PREFIXES = {
  seller: 'sel',
  customer: 'cus',
  admin: 'adm',
} as const satisfies Record<ActorType, IdPrefix>

type ActorPrefix<Type extends ActorType> = (typeof ACTOR_PREFIXES)[Type]

/** The id type one side of the marketplace names its actors by. */
export type ActorIdOf = { [Type in ActorType]: PrefixedId<ActorPrefix<Type>> }

/** What `identityId` has already parsed this request's cookies into, one side
 * of the marketplace at a time. A key holding `null` means that side's cookie
 * was parsed and named no actor — distinct from a key that is absent. */
type ParsedActorIds = { [Type in ActorType]?: ActorIdOf[Type] | null }

/** Who a request is, for the layouts and for anything that renders a header. */
export type Identity = {
  seller: Selectable<SellerTable> | null
  customer: Selectable<CustomerTable> | null
  admin: Selectable<AdminTable> | null
}

declare module 'fastify' {
  interface FastifyRequest {
    currentSeller: Selectable<SellerTable> | null
    currentCustomer: Selectable<CustomerTable> | null
    currentAdmin: Selectable<AdminTable> | null
    identity: Identity
    parsedActorIds: ParsedActorIds | null
  }

  interface FastifyReply {
    signIn<Type extends ActorType>(actorType: Type, actorId: ActorIdOf[Type]): void
    signOut(actorType: ActorType): void
  }
}

/**
 * Decorates every request with the current-actor slots and the cookie stash,
 * and gives replies the cookie writes. It resolves nobody itself: each site
 * that carries a seller or admin runs `resolveSellerIdentity` or
 * `resolveAdminIdentity` as its own preHandler, and the storefront resolves
 * its customer the same way — resolving one can create a row, so only a site
 * that needs that actor pays for the lookup.
 */
export const identityCookies = rootPlugin(
  { name: 'identityCookies', dependencies: ['@fastify/cookie'] },
  (app) => {
    app.decorateRequest('currentSeller', null)
    app.decorateRequest('currentCustomer', null)
    app.decorateRequest('currentAdmin', null)
    app.decorateRequest('parsedActorIds', null)
    app.decorateRequest('identity', {
      getter(this: FastifyRequest): Identity {
        return {
          seller: this.currentSeller,
          customer: this.currentCustomer,
          admin: this.currentAdmin,
        }
      },
    })

    app.decorateReply(
      'signIn',
      function <Type extends ActorType>(
        this: FastifyReply,
        actorType: Type,
        actorId: ActorIdOf[Type],
      ): void {
        this.setCookie(IDENTITY_COOKIES[actorType], actorId, {
          path: '/',
          httpOnly: true,
          sameSite: 'lax',
          signed: true,
          maxAge: COOKIE_LIFETIME_SECONDS,
          secure: this.server.config.secureCookies,
        })
      },
    )

    app.decorateReply('signOut', function (this: FastifyReply, actorType: ActorType): void {
      this.clearCookie(IDENTITY_COOKIES[actorType], { path: '/' })
    })
  },
)

/** Puts the signed-in seller on the request, for a site that carries one. */
export const resolveSellerIdentity: preHandlerAsyncHookHandler = async (request) => {
  request.currentSeller = await findSeller(request)
}

/** Puts the signed-in admin on the request, for a site that carries one. */
export const resolveAdminIdentity: preHandlerAsyncHookHandler = async (request) => {
  request.currentAdmin = await findAdmin(request)
}

/**
 * Reads the identity cookie without loading anything. Returns null when the
 * cookie is absent, unsigned by another secret, or not an id.
 */
export function identityCookieValue(request: FastifyRequest, actorType: ActorType): string | null {
  const cookie = request.cookies[IDENTITY_COOKIES[actorType]]
  if (cookie === undefined) return null

  const unsigned = request.unsignCookie(cookie)

  return unsigned.valid ? unsigned.value : null
}

/**
 * The id a cookie value names on one side of the marketplace, or null when it
 * names nothing that side can be — an id belonging to another table, an old
 * integer id, or anything else a stale or forged cookie carries.
 */
export function parseActorId<Type extends ActorType>(
  actorType: Type,
  value: string | null | undefined,
): PrefixedId<ActorPrefix<Type>> | null {
  if (typeof value !== 'string') return null

  const parsed = parsePrefixedId(ACTOR_PREFIXES[actorType], value)

  return parsed.outcome === 'id' ? parsed.id : null
}

/**
 * The id this request's cookie for one side of the marketplace names. Unsigns
 * the cookie at most once per request: the result is stashed on
 * `request.parsedActorIds` and returned from there on a later call for the
 * same side, however many callers ask within the one request.
 */
export function identityId<Type extends ActorType>(
  request: FastifyRequest,
  actorType: Type,
): ActorIdOf[Type] | null {
  request.parsedActorIds ??= {}
  const cache = request.parsedActorIds

  if (actorType in cache) return cache[actorType] ?? null

  // `parseActorId`'s return is keyed off the same `actorType`, so this is
  // provably the id `ActorIdOf[Type]` names — a form TS can't confirm through
  // two different indexed-access spellings of one generic key.
  const id = parseActorId(actorType, identityCookieValue(request, actorType)) as
    | ActorIdOf[Type]
    | null
  cache[actorType] = id

  return id
}

async function findSeller(request: FastifyRequest): Promise<Selectable<SellerTable> | null> {
  const id = identityId(request, 'seller')
  if (id === null) return null

  const seller = await request.server.db
    .selectFrom('sellers')
    .selectAll()
    .where('id', '=', id)
    .executeTakeFirst()

  return seller ?? null
}

async function findAdmin(request: FastifyRequest): Promise<Selectable<AdminTable> | null> {
  const id = identityId(request, 'admin')
  if (id === null) return null

  const admin = await request.server.db
    .selectFrom('admins')
    .selectAll()
    .where('id', '=', id)
    .executeTakeFirst()

  return admin ?? null
}

/**
 * Every storefront request has a customer behind it, so favorites, a cart, and
 * a guest order have somewhere to hang before anyone gives an address. Writing
 * the cookie back on every request is what rolls a merged id forward.
 */
export const resolveCustomerIdentity: preHandlerAsyncHookHandler = async (request, reply) => {
  const { db, clock } = request.server
  const customer = await resolveCurrentCustomer({ db, clock }, identityId(request, 'customer'))

  request.currentCustomer = customer
  reply.signIn('customer', customer.id)
}

/** Reads the customer the cookie names, and creates nobody when it names none. */
export const rememberCustomerIdentity: preHandlerAsyncHookHandler = async (request) => {
  request.currentCustomer = await resolveCustomerFromCookie(
    { db: request.server.db },
    identityId(request, 'customer'),
  )
}

type SignedInActor = {
  [Type in ActorType]: (identity: Identity) => ActorIdOf[Type] | null
}

const SIGNED_IN_ACTOR: Readonly<SignedInActor> = {
  seller: (identity) => identity.seller?.id ?? null,
  admin: (identity) => identity.admin?.id ?? null,
  // The cookie alone is a browsing history. Only a verified address is an
  // account, so an anonymous customer is signed in as nobody.
  customer: ({ customer }) =>
    customer !== null && isVerifiedCustomer(customer) ? customer.id : null,
}

/** The id this request is signed in as on one side of the marketplace, or null. */
export function signedInActorId<Type extends ActorType>(
  request: FastifyRequest,
  actorType: Type,
): ActorIdOf[Type] | null {
  return SIGNED_IN_ACTOR[actorType](request.identity)
}

function requireActor(actorType: ActorType, alert: string): preHandlerAsyncHookHandler {
  return async (request, reply) => {
    if (signedInActorId(request, actorType) !== null) return undefined

    reply.setFlash({ alert })

    return await reply.redirect(
      `${ACTOR_SITES[actorType].loginPath}?redirect_to=${encodeURIComponent(request.url)}`,
    )
  }
}

export const requireSeller = requireActor('seller', 'Sign in to reach the seller portal.')

export const requireAdmin = requireActor('admin', 'Sign in to reach the admin site.')

export const requireVerifiedCustomer = requireActor(
  'customer',
  'Verify your email address to reach your account.',
)

export const ACTOR_GUARDS: Readonly<Record<ActorType, preHandlerAsyncHookHandler>> = {
  seller: requireSeller,
  customer: requireVerifiedCustomer,
  admin: requireAdmin,
}
