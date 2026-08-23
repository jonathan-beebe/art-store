import type { FastifyInstance, FastifyReply, FastifyRequest, preHandlerHookHandler } from 'fastify'
import type { Selectable } from 'kysely'
import { resolveCurrentCustomer } from '../actions/customers/resolve-current-customer.ts'
import { resolveCustomerFromCookie } from '../actions/customers/resolve-customer-from-cookie.ts'
import { ACTOR_SITES, type ActorType } from '../core/auth/actor-type.ts'
import { isVerifiedCustomer } from '../core/customers/customer-verification.ts'
import type { AdminTable, CustomerTable, SellerTable } from '../db/schema.ts'

/**
 * One cookie per side of the marketplace, so a single browser can be a seller,
 * a customer, and an admin at once — the demo needs that.
 */
const IDENTITY_COOKIES: Readonly<Record<ActorType, string>> = {
  seller: 'seller_id',
  customer: 'customer_id',
  admin: 'admin_id',
}

// A browsing history is worth more than a session, so the cookie outlives one.
const COOKIE_LIFETIME_SECONDS = 365 * 24 * 60 * 60

const ACTOR_ID = /^[0-9]+$/

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
  }

  interface FastifyReply {
    signIn(actorType: ActorType, actorId: number): void
    signOut(actorType: ActorType): void
  }
}

/**
 * Puts the signed-in seller and admin on every request and gives replies the
 * cookie writes. The customer is deliberately absent: resolving one can create
 * a row, so only the storefront's own hook does it.
 */
export function addIdentity(app: FastifyInstance): void {
  app.decorateRequest('currentSeller', null)
  app.decorateRequest('currentCustomer', null)
  app.decorateRequest('currentAdmin', null)
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
    function (this: FastifyReply, actorType: ActorType, actorId: number): void {
      this.setCookie(IDENTITY_COOKIES[actorType], String(actorId), {
        path: '/',
        httpOnly: true,
        sameSite: 'lax',
        signed: true,
        maxAge: COOKIE_LIFETIME_SECONDS,
      })
    },
  )

  app.decorateReply('signOut', function (this: FastifyReply, actorType: ActorType): void {
    this.clearCookie(IDENTITY_COOKIES[actorType], { path: '/' })
  })

  app.addHook('preHandler', resolvePortalIdentities)
}

const resolvePortalIdentities: preHandlerHookHandler = async (request) => {
  request.currentSeller = await findSeller(request)
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

  return unsigned.valid && unsigned.value !== null ? unsigned.value : null
}

/** The id a cookie value names, or null when it names nothing an actor can be. */
export function parseActorId(value: string | null | undefined): number | null {
  if (typeof value !== 'string' || !ACTOR_ID.test(value)) return null

  const id = Number(value)

  return id >= 1 ? id : null
}

/** The id this request's cookie for one side of the marketplace names. */
export function identityId(request: FastifyRequest, actorType: ActorType): number | null {
  return parseActorId(identityCookieValue(request, actorType))
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
export const resolveCustomerIdentity: preHandlerHookHandler = async (request, reply) => {
  const { db, clock } = request.server
  const customer = await resolveCurrentCustomer({ db, clock }, identityId(request, 'customer'))

  request.currentCustomer = customer
  reply.signIn('customer', customer.id)
}

/** Reads the customer the cookie names, and creates nobody when it names none. */
export const rememberCustomerIdentity: preHandlerHookHandler = async (request) => {
  request.currentCustomer = await resolveCustomerFromCookie(
    { db: request.server.db },
    identityId(request, 'customer'),
  )
}

const SIGNED_IN_ACTOR: Readonly<Record<ActorType, (identity: Identity) => number | null>> = {
  seller: (identity) => identity.seller?.id ?? null,
  admin: (identity) => identity.admin?.id ?? null,
  // The cookie alone is a browsing history. Only a verified address is an
  // account, so an anonymous customer is signed in as nobody.
  customer: ({ customer }) =>
    customer !== null && isVerifiedCustomer(customer) ? customer.id : null,
}

/** The id this request is signed in as on one side of the marketplace, or null. */
export function signedInActorId(request: FastifyRequest, actorType: ActorType): number | null {
  return SIGNED_IN_ACTOR[actorType](request.identity)
}

function requireActor(actorType: ActorType, alert: string): preHandlerHookHandler {
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

export const ACTOR_GUARDS: Readonly<Record<ActorType, preHandlerHookHandler>> = {
  seller: requireSeller,
  customer: requireVerifiedCustomer,
  admin: requireAdmin,
}
