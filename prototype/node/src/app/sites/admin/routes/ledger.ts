import type { FastifyPluginCallback } from 'fastify'
import { z } from 'zod'
import { adminPage } from '../page.ts'
import { ledgerRows } from '../queries/ledger-rows.ts'
import { sellerOptions } from '../queries/seller-accounts.ts'
import { LEDGER_ENTRY_TYPES } from '../../../core/escrow/ledger-entry-type.ts'

const filterQuery = z.object({
  seller: z.coerce.number().int().positive().optional(),
  type: z.enum(LEDGER_ENTRY_TYPES).optional().catch(undefined),
})

export const ledgerRoutes: FastifyPluginCallback = (admin, _options, done) => {
  admin.get('/ledger', async (request, reply) => {
    const { seller, type } = filterQuery.parse(request.query)
    const context = { db: admin.db }
    const { rows, totals } = await ledgerRows(context, { sellerId: seller, entryType: type })

    return reply.render(
      'ledger',
      adminPage('Ledger', {
        rows,
        totals,
        sellers: await sellerOptions(context),
        entryTypes: LEDGER_ENTRY_TYPES,
        selectedSeller: seller,
        selectedType: type,
      }),
    )
  })

  done()
}
