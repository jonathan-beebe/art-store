import type { FastifyPluginCallback } from 'fastify'
import { z } from 'zod'
import { adminPage } from '../page.ts'
import { payoutRows } from '../queries/payout-rows.ts'
import { sellerOptions } from '../queries/seller-accounts.ts'
import { runWeeklyPayout } from '../../../actions/escrow/run-weekly-payout.ts'
import { parseAsOfDay } from '../../../core/escrow/payout-day.ts'
import { payoutTotal } from '../../../core/escrow/payout-plan.ts'
import { payoutPeriodEndingBefore, payoutPeriodLabel, type PayoutPeriod } from '../../../core/escrow/payout-period.ts'
import { formatCents } from '../../../core/money.ts'
import type { Payout } from '../../../db/commerce-schema.ts'
import { toTimestamp } from '../../../db/timestamp.ts'
import { formBody } from '../../../plugins/form-body.ts'

const filterQuery = z.object({ seller: z.coerce.number().int().positive().optional() })
const runForm = z.object({ as_of: z.string().optional() })

export const payoutRoutes: FastifyPluginCallback = (admin, _options, done) => {
  admin.get('/payouts', async (request, reply) => {
    const { seller } = filterQuery.parse(request.query)
    const context = { db: admin.db }

    return reply.render(
      'payouts',
      adminPage('Payouts', {
        rows: await payoutRows(context, { sellerId: seller }),
        sellers: await sellerOptions(context),
        selectedSeller: seller,
        today: toTimestamp(admin.clock.now()),
      }),
    )
  })

  admin.post('/payouts', async (request, reply) => {
    const asOf = parseAsOfDay(runForm.parse(formBody(request)).as_of, admin.clock.now())
    const payouts = await runWeeklyPayout({ db: admin.db, clock: admin.clock }, asOf)

    request.log.info(
      { event: 'payout.run', count: payouts.length, totalCents: payoutTotal(payouts) },
      'payout run',
    )
    reply.setFlash({ notice: payoutFlashMessage(payouts, payoutPeriodEndingBefore(asOf)) })

    return reply.redirect('/admin/payouts')
  })

  done()
}

function payoutFlashMessage(payouts: readonly Payout[], period: PayoutPeriod): string {
  const periodLabel = payoutPeriodLabel(period)
  if (payouts.length === 0) return `No seller had a released balance to pay for ${periodLabel}.`

  return `Paid ${payouts.length} seller(s) ${formatCents(payoutTotal(payouts))} for ${periodLabel}.`
}
