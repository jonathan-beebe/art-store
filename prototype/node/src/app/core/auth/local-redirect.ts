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

/**
 * A destination arrives from a form field and rides on a magic link, so
 * anything that could send the visitor off-site or split a response header
 * is dropped rather than repaired.
 */
export function keepLocalRedirect(requested: string | null | undefined, origin: string): string | null {
  const trimmed = (requested ?? '').trim()
  if (trimmed === '' || hasControlCharacter(trimmed)) {
    return null
  }
  return isRootRelative(trimmed) || isOnOrigin(trimmed, origin) ? trimmed : null
}

export function resolveLocalRedirect(
  requested: string | null | undefined,
  options: { fallback: string; origin: string },
): string {
  return keepLocalRedirect(requested, options.origin) ?? options.fallback
}
