import { z } from 'zod'
import { LEDGER_ENTRY_TYPES } from '../../../core/escrow/ledger-entry-type.ts'
import { idValue, optionalFilter } from '../../../http/request-schema.ts'
import type { ZodRoutes } from '../../../http/zod-type-provider.ts'
import { adminPage } from '../page.ts'
import { ledgerRows } from '../queries/ledger-rows.ts'
import { sellerOptions } from '../queries/seller-accounts.ts'

const ledgerQuery = z.object({
  seller: optionalFilter(idValue),
  type: optionalFilter(z.enum(LEDGER_ENTRY_TYPES)),
})

export const ledgerRoutes: ZodRoutes = (admin, _options, done) => {
  admin.get('/ledger', { schema: { querystring: ledgerQuery } }, async (request, reply) => {
    const { seller, type } = request.query
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
