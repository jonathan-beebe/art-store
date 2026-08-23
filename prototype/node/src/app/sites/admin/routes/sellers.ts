import type { FastifyPluginCallback, FastifyReply, FastifyRequest } from 'fastify'
import { shopName } from '../../../core/shop/shop-name.ts'
import { parseIdParam } from '../../../http/id-param.ts'
import { adminPage } from '../page.ts'
import { sellerDetail } from '../queries/seller-detail.ts'
import { sellerRows } from '../queries/seller-rows.ts'

async function index(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  const rows = await sellerRows({ db: request.server.db })

  return reply.render('sellers', adminPage('Sellers', { rows }))
}

async function show(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply | void> {
  const id = parseIdParam(request.params)
  if (id === null) return reply.callNotFound()

  const detail = await sellerDetail({ db: request.server.db }, id)
  if (detail === null) return reply.callNotFound()

  return reply.render('seller', adminPage(shopName(detail.seller), detail))
}

export const sellerRoutes: FastifyPluginCallback = (admin, _options, done) => {
  admin.get('/sellers', index)
  admin.get('/sellers/:id', show)

  done()
}
