import type { FastifyPluginCallback } from 'fastify'
import { z } from 'zod'
import { adminPage } from '../page.ts'
import { ledgerRows } from '../queries/ledger-rows.ts'
import { sellerOptions } from '../queries/seller-accounts.ts'
import { LEDGER_ENTRY_TYPES, type LedgerEntryType } from '../../../core/escrow/ledger-entry-type.ts'

const filterQuery = z.object({ seller: z.string().optional(), type: z.string().optional() })

export const ledgerRoutes: FastifyPluginCallback = (admin, _options, done) => {
  admin.get('/ledger', async (request, reply) => {
    const { seller, type } = filterQuery.parse(request.query)
    const sellerId = parseSellerId(seller)
    const entryType = parseEntryType(type)
    const context = { db: admin.db }
    const { rows, totals } = await ledgerRows(context, { sellerId, entryType })

    return reply.render(
      'ledger',
      adminPage('Ledger', {
        rows,
        totals,
        sellers: await sellerOptions(context),
        entryTypes: LEDGER_ENTRY_TYPES,
        selectedSeller: sellerId,
        selectedType: entryType,
      }),
    )
  })

  done()
}

function parseSellerId(value: string | undefined): number | undefined {
  if (value === undefined || value === '') return undefined

  const id = Number(value)

  return Number.isInteger(id) && id > 0 ? id : undefined
}

function parseEntryType(value: string | undefined): LedgerEntryType | undefined {
  return (LEDGER_ENTRY_TYPES as readonly string[]).includes(value ?? '')
    ? (value as LedgerEntryType)
    : undefined
}
