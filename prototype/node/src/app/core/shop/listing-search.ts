/** What a storefront visitor asked to see: free text over the catalogue and a
 * medium to narrow it to. Either half may be absent. */
export type ListingSearch = {
  term: string | null
  medium: string | null
}

function filled(value: string | null | undefined): string | null {
  const text = (value ?? '').trim()

  return text.length === 0 ? null : text
}

export function parseListingSearch(input: {
  term?: string | null
  medium?: string | null
}): ListingSearch {
  return { term: filled(input.term), medium: filled(input.medium) }
}

/**
 * The `LIKE` pattern for a search that has a term. SQLite has no escape
 * character unless the query names one, so a wildcard the visitor typed is
 * turned into a space rather than escaped.
 */
export function searchLikePattern(search: ListingSearch): string {
  if (search.term === null) {
    throw new RangeError('a search without a term has no pattern')
  }

  const literal = search.term.replace(/[%_]/g, ' ').replace(/\s+/g, ' ').trim()

  return `%${literal}%`
}
