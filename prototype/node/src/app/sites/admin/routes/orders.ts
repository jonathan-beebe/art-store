import { z } from 'zod'
import { cancelOrderAsAdmin } from '../../../actions/orders/cancel-order-as-admin.ts'
import { isCancellable, ORDER_STATUSES } from '../../../core/orders/order-status.ts'
import { listPage } from '../../../core/paging/list-page.ts'
import { parseRefundReason, REFUND_REASON_MAX_LENGTH } from '../../../core/orders/refund.ts'
import { TransitionError } from '../../../core/transition-error.ts'
import { idParams, idValue, optionalFilter, submittedForm } from '../../../http/request-schema.ts'
import { requestActions } from '../../../http/request-actions.ts'
import type { ZodRoutes } from '../../../http/zod-type-provider.ts'
import { adminPage } from '../page.ts'
import { orderDetail } from '../queries/order-detail.ts'
import { countOrderRows, orderRows } from '../queries/order-rows.ts'

const cancelForm = submittedForm({ reason: z.string().optional() })

// One screen of the orders table.
const ROWS_PER_PAGE = 25

const ordersQuery = z.object({
  status: optionalFilter(z.enum(ORDER_STATUSES)),
  customer: optionalFilter(idValue('cus')),
  page: z.string().optional(),
})

export const orderRoutes: ZodRoutes = (admin, _options, done) => {
  admin.get('/orders', { schema: { querystring: ordersQuery } }, async (request, reply) => {
    const { status, customer } = request.query
    const filters = { status, customerId: customer }
    const page = listPage({
      requested: request.query.page,
      size: ROWS_PER_PAGE,
      totalCount: await countOrderRows({ db: admin.db }, filters),
    })
    const orders = await orderRows({ db: admin.db }, filters, page)

    return reply.render(
      'orders',
      adminPage('Orders', {
        orders,
        statuses: ORDER_STATUSES,
        filters: { status: status ?? '', customer: customer ?? '' },
        page,
        filterQuery: new URLSearchParams({
          ...(status !== undefined ? { status } : {}),
          ...(customer !== undefined ? { customer } : {}),
        }).toString(),
      }),
    )
  })

  admin.get('/orders/:id', { schema: { params: idParams('ord') } }, async (request, reply) => {
    const detail = await orderDetail({ db: admin.db }, request.params.id)
    if (detail === null) return reply.callNotFound()

    return reply.render(
      'order',
      adminPage(`Order ${detail.order.id}`, {
        ...detail,
        canCancel: isCancellable(detail.order.status),
        reasonMaxLength: REFUND_REASON_MAX_LENGTH,
      }),
    )
  })

  admin.post(
    '/orders/:id/cancel',
    { schema: { params: idParams('ord'), body: cancelForm } },
    async (request, reply) => {
      const orderId = request.params.id
      const destination = `/admin/orders/${orderId}`
      const order = await admin.db
        .selectFrom('orders')
        .select('id')
        .where('id', '=', orderId)
        .executeTakeFirst()
      if (order === undefined) return reply.callNotFound()

      const reason = parseRefundReason(request.body.reason)
      if (!reason.ok) {
        reply.setFlash({ alert: Object.values(reason.errors)[0] })

        return reply.redirect(destination)
      }

      try {
        await cancelOrderAsAdmin(requestActions(request), { orderId, reason: reason.value })
      } catch (error) {
        if (!(error instanceof TransitionError)) throw error

        reply.setFlash({ alert: error.message })

        return reply.redirect(destination)
      }

      reply.setFlash({ notice: 'Order cancelled.' })

      return reply.redirect(destination)
    },
  )

  done()
}
