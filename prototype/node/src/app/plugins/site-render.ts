import type { FastifyInstance, FastifyReply } from 'fastify'

export type SiteRenderOptions = {
  /** Directory holding the site's page templates, relative to the view root. */
  pages: string
  /** The site's layout template, relative to the view root. */
  layout: string
}

declare module 'fastify' {
  interface FastifyReply {
    render(page: string, data?: Record<string, unknown>): FastifyReply
  }
}

/**
 * Gives one site a `reply.render(page)` that finds the page among that site's
 * templates, wraps it in that site's layout, and hands every layout the flash —
 * so a route reads as one line and no route can forget the debug alert.
 *
 * Called inside a site plugin, never at the root: each site needs its own
 * layout, and Fastify keeps the decorator inside the context that added it.
 */
export function addSiteRender(site: FastifyInstance, options: SiteRenderOptions): void {
  site.decorateReply(
    'render',
    function (this: FastifyReply, page: string, data: Record<string, unknown> = {}) {
      return this.view(
        `${options.pages}/${page}`,
        { ...data, flash: this.takeFlash() },
        { layout: options.layout },
      )
    },
  )
}
