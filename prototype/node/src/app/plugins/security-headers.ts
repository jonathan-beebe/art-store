import { rootPlugin } from './root-plugin.ts'

/**
 * The policy every response carries. `data:` is in `img-src` because a listing
 * with no photograph renders a generated SVG placeholder inline; nothing else
 * loads from anywhere but this origin, and the one script a layout carries is
 * served from it.
 */
const CONTENT_SECURITY_POLICY = [
  "default-src 'self'",
  "img-src 'self' data:",
  "style-src 'self'",
  "script-src 'self'",
  "form-action 'self'",
  "frame-ancestors 'none'",
].join('; ')

const SECURITY_HEADERS: Readonly<Record<string, string>> = {
  'X-Content-Type-Options': 'nosniff',
  'X-Frame-Options': 'DENY',
  'Referrer-Policy': 'strict-origin-when-cross-origin',
  'Content-Security-Policy': CONTENT_SECURITY_POLICY,
}

/**
 * One hook at the root, so a page, a JSON health check, an uploaded file, and
 * a 404 all answer with the same headers and no route can forget them.
 */
export const securityHeaders = rootPlugin({ name: 'securityHeaders' }, (app) => {
  app.addHook('onSend', async (_request, reply) => {
    reply.headers(SECURITY_HEADERS)
  })
})
