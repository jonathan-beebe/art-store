import type { FastifyPluginCallback, FastifyReply } from 'fastify'
import { z } from 'zod'
import { sendMagicLink } from '../../../actions/auth/send-magic-link.ts'
import type { CartContents } from '../../../actions/carts/cart-contents.ts'
import { cartContents } from '../../../actions/carts/cart-contents.ts'
import { currentCart } from '../../../actions/carts/current-cart.ts'
import { finalizeOrder } from '../../../actions/orders/finalize-order.ts'
import { placeOrder, type PlacedOrder, type PlaceOrderInput } from '../../../actions/orders/place-order.ts'
import { runInTransaction } from '../../../actions/transaction.ts'
import type { ActionContext } from '../../../actions/action-context.ts'
import { parseCheckoutForm } from '../../../core/shop/checkout-form.ts'
import { purchaserForCheckout } from '../../../core/shop/checkout-purchaser.ts'
import { unavailableNotices, type UnavailableNotice } from '../../../core/orders/order-placement.ts'
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
  unavailable: readonly UnavailableNotice[]
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
      unavailable: view.unavailable,
      lines: view.contents.lines,
      totals: view.contents.totals,
    }),
  )
}

type CheckOutCartInput = PlaceOrderInput & { cardNumber: string }

/**
 * Placing the order and charging the card as one unit, so a failure part-way
 * through leaves neither an order nor a payment behind.
 */
async function checkOutCart(context: ActionContext, input: CheckOutCartInput): Promise<PlacedOrder> {
  return runInTransaction(context, async (transacted) => {
    const placement = await placeOrder(transacted, input)
    if (!placement.ok) return placement

    if (isPayable(placement.order.status, input.purchaser.isEmailVerified)) {
      await finalizeOrder(transacted, { orderId: placement.order.id, cardNumber: input.cardNumber })
    }

    return placement
  })
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
      unavailable: [],
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
    const parsed = parseCheckoutForm({ email: submitted.email, shipping: shippingFromForm(submitted) })

    if (!parsed.ok) {
      return renderCheckout(
        reply,
        {
          email: parsed.entered.email,
          shipping: parsed.entered.shipping,
          isVerified,
          missingParts: parsed.errors,
          unavailable: [],
          contents,
        },
        422,
      )
    }

    const form = parsed.value

    const purchaser = purchaserForCheckout({
      customerId: customer.id,
      accountEmail: customer.email,
      isAccountVerified: isVerified,
      submittedEmail: form.email,
    })

    const placement = await checkOutCart(
      { db, clock },
      {
        cartId: cart.id,
        purchaser,
        shipping: form.shipping,
        cardNumber: submitted.card_number ?? '',
      },
    )

    if (!placement.ok) {
      return renderCheckout(
        reply,
        {
          email: form.email,
          shipping: form.shipping,
          isVerified,
          missingParts: [],
          unavailable: unavailableNotices(placement.unavailable),
          contents,
        },
        422,
      )
    }

    const order = placement.order

    if (isPayable(order.status, purchaser.isEmailVerified)) {
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
