import type { FastifyPluginCallback, FastifyReply, FastifyRequest } from 'fastify'
import { z } from 'zod'
import { adminPage } from '../page.ts'
import { sellerDetail } from '../queries/seller-detail.ts'
import { sellerRows } from '../queries/seller-rows.ts'

const idParams = z.object({ id: z.coerce.number().int().positive() })

function parseSellerId(params: unknown): number | null {
  const parsed = idParams.safeParse(params)

  return parsed.success ? parsed.data.id : null
}

async function index(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply> {
  const rows = await sellerRows({ db: request.server.db })

  return reply.render('sellers', adminPage('Sellers', { rows }))
}

async function show(request: FastifyRequest, reply: FastifyReply): Promise<FastifyReply | void> {
  const id = parseSellerId(request.params)
  if (id === null) return reply.callNotFound()

  const detail = await sellerDetail({ db: request.server.db }, id)
  if (detail === null) return reply.callNotFound()

  const title = detail.seller.shopName ?? detail.seller.email

  return reply.render('seller', adminPage(title, detail))
}

export const sellerRoutes: FastifyPluginCallback = (admin, _options, done) => {
  admin.get('/sellers', index)
  admin.get('/sellers/:id', show)

  done()
}
