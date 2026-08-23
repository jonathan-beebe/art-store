import type { FastifyPluginCallback, FastifyReply } from 'fastify'
import { z } from 'zod'
import { sendMagicLink } from '../../../actions/auth/send-magic-link.ts'
import type { CartContents } from '../../../actions/carts/cart-contents.ts'
import { cartContents } from '../../../actions/carts/cart-contents.ts'
import { currentCart } from '../../../actions/carts/current-cart.ts'
import { finalizeOrder } from '../../../actions/orders/finalize-order.ts'
import { placeOrder } from '../../../actions/orders/place-order.ts'
import {
  isCheckoutComplete,
  missingCheckoutParts,
  parseCheckoutForm,
} from '../../../core/shop/checkout-form.ts'
import { purchaserForCheckout } from '../../../core/shop/checkout-purchaser.ts'
import { isPayable } from '../../../core/orders/order-payment.ts'
import type { ShippingAddress } from '../../../core/orders/shipping-address.ts'
import { formBody } from '../../../plugins/form-body.ts'
import { signedInActorId } from '../../../plugins/identity.ts'
import { magicLinkUrl } from '../../auth/request-origin.ts'
import { SHIPPING_FIELDS, shippingFromForm } from '../checkout-fields.ts'
import { refuseBlockedCustomer } from '../refuse-blocked-customer.ts'
import { shopPage } from '../shop-page.ts'
import { storefrontCustomer } from '../storefront-customer.ts'

const checkoutBody = z.object({
  email: z.string().optional(),
  card_number: z.string().optional(),
  shipping_name: z.string().optional(),
  shipping_line1: z.string().optional(),
  shipping_line2: z.string().optional(),
  shipping_city: z.string().optional(),
  shipping_region: z.string().optional(),
  shipping_postal_code: z.string().optional(),
  shipping_country: z.string().optional(),
})

type CheckoutView = {
  email: string
  shipping: Partial<Record<keyof ShippingAddress, string | null>>
  isVerified: boolean
  missingParts: readonly string[]
  contents: CartContents
}

function renderCheckout(reply: FastifyReply, view: CheckoutView, status = 200): FastifyReply {
  return reply.status(status).render(
    'checkout',
    shopPage({
      title: 'Checkout',
      fields: SHIPPING_FIELDS,
      email: view.email,
      shipping: view.shipping,
      isVerified: view.isVerified,
      missingParts: view.missingParts,
      lines: view.contents.lines,
      totals: view.contents.totals,
    }),
  )
}

export const checkoutRoutes: FastifyPluginCallback = (shop, _options, done) => {
  const { db, clock } = shop
  const guardBlocked = refuseBlockedCustomer(() => '/cart')

  shop.get('/checkout', { preHandler: guardBlocked }, async (request, reply) => {
    const customer = storefrontCustomer(request)
    const cart = await currentCart({ db, clock }, customer.id)
    const contents = await cartContents({ db }, cart.id)
    if (contents.lines.length === 0) return await reply.redirect('/cart')

    const isVerified = signedInActorId(request, 'customer') !== null

    return renderCheckout(reply, {
      email: isVerified ? (customer.email ?? '') : '',
      shipping: {},
      isVerified,
      missingParts: [],
      contents,
    })
  })

  shop.post('/checkout', { preHandler: guardBlocked }, async (request, reply) => {
    const customer = storefrontCustomer(request)
    const cart = await currentCart({ db, clock }, customer.id)
    const contents = await cartContents({ db }, cart.id)
    if (contents.lines.length === 0) return await reply.redirect('/cart')

    const submitted = checkoutBody.parse(formBody(request))
    const isVerified = signedInActorId(request, 'customer') !== null
    const form = parseCheckoutForm({ email: submitted.email, shipping: shippingFromForm(submitted) })

    if (!isCheckoutComplete(form)) {
      return renderCheckout(
        reply,
        {
          email: form.email,
          shipping: form.shipping,
          isVerified,
          missingParts: missingCheckoutParts(form),
          contents,
        },
        422,
      )
    }

    const purchaser = purchaserForCheckout({
      customerId: customer.id,
      accountEmail: customer.email,
      isAccountVerified: isVerified,
      submittedEmail: form.email,
    })

    const order = await placeOrder({ db, clock }, { cartId: cart.id, purchaser, shipping: form.shipping })

    if (isPayable(order.status, purchaser.isEmailVerified)) {
      await finalizeOrder(
        { db, clock },
        { orderId: order.id, cardNumber: submitted.card_number ?? '' },
      )

      return await reply.redirect(`/orders/${order.id}`)
    }

    const delivered = await sendMagicLink(
      { db, clock, delivery: shop.magicLinkDelivery, magicLinkUrl: (token) => magicLinkUrl(request, token) },
      { email: purchaser.email ?? '', actorType: 'customer', redirectTo: `/orders/${order.id}/pay` },
    )

    reply.setFlash({ ...delivered, notice: 'Check your email to verify your address and pay.' })

    return await reply.redirect(`/orders/${order.id}`)
  })

  done()
}
