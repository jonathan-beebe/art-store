import type { FastifyInstance, FastifyReply } from 'fastify'
import { csrfTokenForRequest } from './csrf.ts'

export type SiteRenderOptions = {
  /** Directory holding the site's page templates, relative to the view root. */
  pages: string
  /** The site's layout template, relative to the view root. */
  layout: string
}

/** One site's pages, rendered onto any reply the app holds. */
export type SitePageRenderer = (
  reply: FastifyReply,
  page: string,
  data?: Record<string, unknown>,
) => FastifyReply

declare module 'fastify' {
  interface FastifyReply {
    render(page: string, data?: Record<string, unknown>): FastifyReply
  }
}

/**
 * Gives one site a `reply.render(page)` that finds the page among that site's
 * templates, wraps it in that site's layout, and hands every layout the flash,
 * the request's identity, what is waiting in the messages inbox, whether this
 * deployment prints sign-in links into the page, and the CSRF token this
 * browser's next form submits — so a route reads as one line, and no route
 * can forget the debug alert, the header's sign-in state, its unread count,
 * or the token every form now carries.
 *
 * Called inside a site plugin, never at the root: each site needs its own
 * layout, and Fastify keeps the decorator inside the context that added it.
 * The renderer it returns is the same page-writing for a reply that never
 * reached this site's routes and so carries no `render` of its own.
 */
export function addSiteRender(
  site: FastifyInstance,
  options: SiteRenderOptions,
): SitePageRenderer {
  const renderPage: SitePageRenderer = (reply, page, data = {}) =>
    reply.view(
      `${options.pages}/${page}`,
      {
        ...data,
        flash: reply.takeFlash(),
        identity: reply.request.identity,
        unreadMessageCount: reply.request.unreadMessageCount,
        showsDebugMagicLinks: reply.server.config.showsDebugMagicLinks,
        csrfToken: csrfTokenForRequest(reply.request),
      },
      { layout: options.layout },
    )

  site.decorateReply(
    'render',
    function (this: FastifyReply, page: string, data: Record<string, unknown> = {}) {
      return renderPage(this, page, data)
    },
  )

  return renderPage
}
