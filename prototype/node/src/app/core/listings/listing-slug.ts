const FALLBACK = 'listing'

export function slugBase(title: string): string {
  const slug = title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')
  return slug.length === 0 ? FALLBACK : slug
}

// Titles repeat between sellers, so a slug that is already taken counts up
// until it finds one that is free.
export function firstFreeSlug(title: string, taken: readonly string[]): string {
  const candidate = slugBase(title)
  if (!taken.includes(candidate)) {
    return candidate
  }

  let suffix = 2
  while (taken.includes(`${candidate}-${suffix}`)) {
    suffix += 1
  }
  return `${candidate}-${suffix}`
}
