/**
 * What a request line is allowed to show as its path. Most urls are safe as
 * they arrived, but a magic-link url carries the sign-in token in a segment,
 * and a token in the log stream is a token anyone reading the log can sign in
 * with. For those routes the pattern stands in for the url.
 */

/** Routes whose url carries a secret, named by the pattern they matched. */
const SECRET_ROUTES: ReadonlySet<string> = new Set(['/auth/magic/:token'])

/** The path without its query string. */
export function pathnameOf(url: string): string {
  const query = url.indexOf('?')

  return query === -1 ? url : url.slice(0, query)
}

/**
 * The path a log line may show for a request: the url's own path, or the route
 * pattern where a segment of that url is a secret. `routePattern` is undefined
 * for a request that matched no route.
 */
export function loggablePath(url: string, routePattern: string | undefined): string {
  if (routePattern !== undefined && SECRET_ROUTES.has(routePattern)) return routePattern

  return pathnameOf(url)
}
