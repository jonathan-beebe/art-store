import { shopName } from '../../../core/shop/shop-name.ts'
import { idParams } from '../../../http/request-schema.ts'
import type { ZodRoutes } from '../../../http/zod-type-provider.ts'
import { adminPage } from '../page.ts'
import { sellerDetail } from '../queries/seller-detail.ts'
import { sellerRows } from '../queries/seller-rows.ts'

export const sellerRoutes: ZodRoutes = (admin, _options, done) => {
  admin.get('/sellers', async (_request, reply) =>
    reply.render('sellers', adminPage('Sellers', { rows: await sellerRows({ db: admin.db }) })),
  )

  admin.get('/sellers/:id', { schema: { params: idParams('sel') } }, async (request, reply) => {
    const detail = await sellerDetail({ db: admin.db }, request.params.id)
    if (detail === null) return reply.callNotFound()

    return reply.render('seller', adminPage(shopName(detail.seller), detail))
  })

  done()
}
