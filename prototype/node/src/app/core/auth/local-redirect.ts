import type { ActorType } from './actor-type.ts'

function hasControlCharacter(target: string): boolean {
  for (let i = 0; i < target.length; i += 1) {
    const code = target.charCodeAt(i)
    if (code <= 0x1f || code === 0x7f) {
      return true
    }
  }
  return false
}

function isRootRelative(target: string): boolean {
  return target.startsWith('/') && !target.startsWith('//') && !target.startsWith('/\\')
}

function isOnOrigin(target: string, origin: string): boolean {
  return target === origin || target.startsWith(`${origin}/`)
}

const SELLER_PATH_PREFIX = '/seller'
const ADMIN_PATH_PREFIX = '/admin'

function hasPathPrefix(path: string, prefix: string): boolean {
  return path === prefix || path.startsWith(`${prefix}/`)
}

/**
 * The prefixes each actor type holds no guard for. A seller and an admin
 * each hold the one guard the other lacks, so a seller may not be sent to an
 * admin path and an admin may not be sent to a seller path; a customer holds
 * neither guard and is refused both.
 */
const FORBIDDEN_PATH_PREFIXES: Readonly<Record<ActorType, readonly string[]>> = {
  seller: [ADMIN_PATH_PREFIX],
  customer: [SELLER_PATH_PREFIX, ADMIN_PATH_PREFIX],
  admin: [SELLER_PATH_PREFIX],
}

/** Whether `actorType` may be sent to `path` once signed in. */
export function allowsPath(actorType: ActorType, path: string): boolean {
  return FORBIDDEN_PATH_PREFIXES[actorType].every((prefix) => !hasPathPrefix(path, prefix))
}

/** The path segment of a target already known to be root-relative or
 * origin-prefixed, stripped of any query or fragment and with any `.`/`..`
 * segment collapsed — what `allowsPath` reads. Resolving through `URL`
 * (against a placeholder base, so a root-relative target parses the same as
 * an origin-prefixed one) collapses dot segments, including their
 * percent-encoded form (`%2e`), the same way a browser collapses them from a
 * `Location` header before it requests the redirected page — so a path that
 * reads as safe before collapsing cannot smuggle a different one past
 * `allowsPath`. */
function pathOf(target: string): string {
  const withoutFragment = target.split('#')[0] ?? target

  return new URL(withoutFragment, 'http://placeholder').pathname
}

/**
 * A destination arrives from a form field and rides on a magic link, so
 * anything that could send the visitor off-site or split a response header is
 * dropped rather than repaired. A target that stays on-site but names a path
 * the signing-in actor holds no guard for is dropped the same way, so a
 * seller-site link cannot carry a visitor onto an admin path.
 */
export function keepLocalRedirect(
  requested: string | null | undefined,
  actorType: ActorType,
  origin: string,
): string | null {
  const trimmed = (requested ?? '').trim()
  if (trimmed === '' || hasControlCharacter(trimmed)) {
    return null
  }

  const isLocal = isRootRelative(trimmed) || isOnOrigin(trimmed, origin)

  return isLocal && allowsPath(actorType, pathOf(trimmed)) ? trimmed : null
}

export function resolveLocalRedirect(
  requested: string | null | undefined,
  options: { actorType: ActorType; fallback: string; origin: string },
): string {
  return keepLocalRedirect(requested, options.actorType, options.origin) ?? options.fallback
}
