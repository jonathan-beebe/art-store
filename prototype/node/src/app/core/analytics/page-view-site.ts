export const PAGE_VIEW_SITES = ['shop', 'seller', 'admin'] as const
export type PageViewSite = (typeof PAGE_VIEW_SITES)[number]

const PORTAL_PREFIXES: readonly (readonly [string, PageViewSite])[] = [
  ['/seller', 'seller'],
  ['/admin', 'admin'],
]

/**
 * Which side of the marketplace a path belongs to. The storefront has no
 * prefix of its own, so it is what a path is when no portal claims it — which
 * also keeps `/sellers-guide` on the storefront where it belongs.
 */
export function pageViewSite(path: string): PageViewSite {
  const claimed = PORTAL_PREFIXES.find(
    ([prefix]) => path === prefix || path.startsWith(`${prefix}/`),
  )

  return claimed === undefined ? 'shop' : claimed[1]
}
