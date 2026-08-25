import { rootPlugin } from './root-plugin.ts'

/**
 * The policy every response carries. Every image, including the generated
 * placeholder a listing with no photograph renders, loads from this origin,
 * so `img-src` names nothing but `'self'`; the one script a layout carries is
 * served from it too.
 */
const CONTENT_SECURITY_POLICY = [
  "default-src 'self'",
  "img-src 'self'",
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
