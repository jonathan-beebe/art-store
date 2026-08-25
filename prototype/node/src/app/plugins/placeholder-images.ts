import { z } from 'zod'
import { placeholderImageSvg } from '../core/listings/placeholder-image.ts'
import type { ZodRoutes } from '../http/zod-type-provider.ts'

const placeholderParams = z.object({ title: z.string().min(1) })

const ONE_WEEK_SECONDS = 604_800

/**
 * A listing's generated placeholder picture, at the path
 * `listingImageSource` falls back to. Registered at the root — outside
 * every site — so a shop page and a seller page both render it. The svg is
 * regenerated per request rather than stored: `placeholderImageSvg` is pure
 * and cheap, so there is nothing to cache but the response itself, which the
 * long `Cache-Control` here hands to the browser.
 */
export const placeholderImages: ZodRoutes = (app, _options, done) => {
  app.get('/placeholders/:title', { schema: { params: placeholderParams } }, async (request, reply) => {
    reply.header('Content-Type', 'image/svg+xml')
    reply.header('Cache-Control', `public, max-age=${ONE_WEEK_SECONDS}, immutable`)

    return placeholderImageSvg(request.params.title)
  })

  done()
}
