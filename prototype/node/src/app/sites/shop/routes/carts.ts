import type { FastifyPluginCallback } from 'fastify'
import { z } from 'zod'
import { addToCart } from '../../../actions/carts/add-to-cart.ts'
import { cartContents } from '../../../actions/carts/cart-contents.ts'
import { currentCart } from '../../../actions/carts/current-cart.ts'
import { removeFromCart } from '../../../actions/carts/remove-from-cart.ts'
import { currentCustomerStanding } from '../../../actions/moderation/current-customer-standing.ts'
import { canShop } from '../../../core/moderation/customer-standing.ts'
import { blockedShopperNotice } from '../../../core/shop/blocked-shopper-notice.ts'
import { formBody } from '../../../plugins/form-body.ts'
import { findListingBySlug } from '../queries/find-listing-by-slug.ts'
import { findListingOnStorefront } from '../queries/find-listing-on-storefront.ts'
import { refuseBlockedCustomer } from '../refuse-blocked-customer.ts'
import { renderNotFound, shopPage } from '../shop-page.ts'
import { storefrontCustomer } from '../storefront-customer.ts'

const SOLD_OUT_ALERT = 'That listing is no longer for sale.'

const parameters = z.object({ slug: z.string() })

// Undefined or junk means one: the quantity field only appears on the page
// for a listing with more than one in stock.
const addForm = z.object({ quantity: z.coerce.number().int().min(1).catch(1) }).catch({ quantity: 1 })

export const cartRoutes: FastifyPluginCallback = (shop, _options, done) => {
  shop.get('/cart', async (request, reply) => {
    const { db, clock } = shop
    const customer = storefrontCustomer(request)
    const cart = await currentCart({ db, clock }, customer.id)
    const contents = await cartContents({ db }, cart.id)
    const standing = await currentCustomerStanding({ db }, customer.id)

    return reply.render(
      'cart',
      shopPage({
        title: 'Cart',
        lines: contents.lines,
        totals: contents.totals,
        canShop: canShop(standing),
        blockedNotice: blockedShopperNotice(standing),
      }),
    )
  })

  shop.post(
    '/cart/:slug',
    { preHandler: refuseBlockedCustomer((request) => `/art/${parameters.parse(request.params).slug}`) },
    async (request, reply) => {
      const { db, clock } = shop
      const { slug } = parameters.parse(request.params)
      const found = await findListingOnStorefront(db, slug)
      if (found === null) return renderNotFound(reply)

      if (!found.isPurchasable) {
        reply.setFlash({ alert: SOLD_OUT_ALERT })
        return reply.redirect(`/art/${slug}`)
      }

      const customer = storefrontCustomer(request)
      const cart = await currentCart({ db, clock }, customer.id)
      const { quantity } = addForm.parse(formBody(request))
      await addToCart({ db, clock }, { cartId: cart.id, listingId: found.listing.id, quantity })

      return reply.redirect('/cart')
    },
  )

  // Taking a line out works whatever became of the listing: a piece that left
  // the storefront is exactly the one a customer wants out of their cart.
  shop.post('/cart/:slug/remove', async (request, reply) => {
    const { db, clock } = shop
    const { slug } = parameters.parse(request.params)
    const found = await findListingBySlug(db, slug)
    if (found === null) return renderNotFound(reply)

    const customer = storefrontCustomer(request)
    const cart = await currentCart({ db, clock }, customer.id)
    await removeFromCart({ db, clock }, { cartId: cart.id, listingId: found.listing.id })

    return reply.redirect('/cart')
  })

  done()
}
