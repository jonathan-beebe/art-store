import { z } from 'zod'
import { addToCart } from '../../../actions/carts/add-to-cart.ts'
import { cartContents } from '../../../actions/carts/cart-contents.ts'
import { currentCart } from '../../../actions/carts/current-cart.ts'
import { removeFromCart } from '../../../actions/carts/remove-from-cart.ts'
import { currentCustomerStanding } from '../../../actions/moderation/current-customer-standing.ts'
import { runInTransaction } from '../../../actions/transaction.ts'
import { canShop } from '../../../core/moderation/customer-standing.ts'
import { parseCartQuantity } from '../../../core/shop/cart-quantity.ts'
import { blockedShopperNotice } from '../../../core/shop/blocked-shopper-notice.ts'
import { slugParams, submittedForm, type SlugParams } from '../../../http/request-schema.ts'
import type { ZodRoutes } from '../../../http/zod-type-provider.ts'
import { requestActions } from '../../../http/request-actions.ts'
import { renderListingPage } from '../listing-page.ts'
import { findListingBySlug } from '../queries/find-listing-by-slug.ts'
import { findListingOnStorefront } from '../queries/find-listing-on-storefront.ts'
import { refuseBlockedCustomer } from '../refuse-blocked-customer.ts'
import { renderNotFound, shopPage } from '../shop-page.ts'
import { storefrontCustomer } from '../storefront-customer.ts'

const SOLD_OUT_ALERT = 'That listing is no longer for sale.'

// The quantity arrives as the visitor typed it — `parseCartQuantity` is what
// decides whether it is a whole number the stock on hand allows.
const addForm = submittedForm({ quantity: z.string().optional() })

export const cartRoutes: ZodRoutes = (shop, _options, done) => {
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
    {
      schema: { params: slugParams, body: addForm },
      preHandler: refuseBlockedCustomer(({ slug }: SlugParams) => `/art/${slug}`),
    },
    async (request, reply) => {
      const { slug } = request.params
      const customer = storefrontCustomer(request)

      const found = await findListingOnStorefront(shop.db, slug)
      if (found === null) return renderNotFound(reply)

      const parsedQuantity = parseCartQuantity(request.body.quantity, found.listing.quantity)
      if (!parsedQuantity.ok) {
        return renderListingPage(
          shop,
          request,
          reply,
          slug,
          {
            cartQuantity: request.body.quantity ?? '',
            cartErrors: parsedQuantity.errors,
          },
          422,
        )
      }

      // The gate and the line it writes read one snapshot of the listing, so a
      // piece removed or taken off sale mid-request never lands in a cart.
      const outcome = await runInTransaction(requestActions(request), async (transacted) => {
        const current = await findListingOnStorefront(transacted.db, slug)
        if (current === null) return 'unknown' as const
        if (!current.isPurchasable) return 'unavailable' as const

        const cart = await currentCart(transacted, customer.id)
        await addToCart(transacted, {
          cartId: cart.id,
          listingId: current.listing.id,
          quantity: parsedQuantity.value,
        })

        return 'added' as const
      })

      if (outcome === 'unknown') return renderNotFound(reply)

      if (outcome === 'unavailable') {
        return renderListingPage(shop, request, reply, slug, { cartFormError: SOLD_OUT_ALERT }, 422)
      }

      return reply.redirect('/cart')
    },
  )

  // Taking a line out works whatever became of the listing: a piece that left
  // the storefront is exactly the one a customer wants out of their cart.
  shop.post('/cart/:slug/remove', { schema: { params: slugParams } }, async (request, reply) => {
    const { db, clock } = shop
    const { slug } = request.params
    const found = await findListingBySlug(db, slug)
    if (found === null) return renderNotFound(reply)

    const customer = storefrontCustomer(request)
    const cart = await currentCart({ db, clock }, customer.id)
    await removeFromCart(requestActions(request), { cartId: cart.id, listingId: found.listing.id })

    return reply.redirect('/cart')
  })

  done()
}
