import { z } from 'zod'
import { runWeeklyPayout } from '../../../actions/escrow/run-weekly-payout.ts'
import { parseAsOfDay } from '../../../core/escrow/payout-day.ts'
import { payoutTotal } from '../../../core/escrow/payout-plan.ts'
import {
  payoutPeriodEndingBefore,
  payoutPeriodLabel,
  type PayoutPeriod,
} from '../../../core/escrow/payout-period.ts'
import { formatCents } from '../../../core/money.ts'
import type { Payout } from '../../../db/commerce-schema.ts'
import { toTimestamp } from '../../../db/timestamp.ts'
import { idValue, optionalFilter, submittedForm } from '../../../http/request-schema.ts'
import type { ZodRoutes } from '../../../http/zod-type-provider.ts'
import { requestActions } from '../../../http/request-actions.ts'
import { adminPage } from '../page.ts'
import { payoutRows } from '../queries/payout-rows.ts'
import { sellerOptions } from '../queries/seller-accounts.ts'

const payoutsQuery = z.object({ seller: optionalFilter(idValue('sel')) })
const runForm = submittedForm({ as_of: z.string().optional() })

export const payoutRoutes: ZodRoutes = (admin, _options, done) => {
  admin.get('/payouts', { schema: { querystring: payoutsQuery } }, async (request, reply) => {
    const { seller } = request.query
    const context = { db: admin.db }

    return reply.render(
      'payouts',
      adminPage('Payouts', {
        rows: await payoutRows(context, { sellerId: seller }),
        sellers: await sellerOptions(context),
        selectedSeller: seller,
        asOfDate: toTimestamp(admin.clock.now()).slice(0, 10),
      }),
    )
  })

  admin.post('/payouts', { schema: { body: runForm } }, async (request, reply) => {
    const asOf = parseAsOfDay(request.body.as_of, admin.clock.now())
    const payouts = await runWeeklyPayout(requestActions(request), asOf)

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
