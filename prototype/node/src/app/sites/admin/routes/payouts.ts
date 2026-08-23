import type { FastifyPluginCallback } from 'fastify'
import { z } from 'zod'
import { adminPage } from '../page.ts'
import { payoutRows } from '../queries/payout-rows.ts'
import { sellerOptions } from '../queries/seller-accounts.ts'
import { runWeeklyPayout } from '../../../actions/escrow/run-weekly-payout.ts'
import { payoutPeriodEndingBefore, payoutPeriodLabel, type PayoutPeriod } from '../../../core/escrow/payout-period.ts'
import { addCents, formatCents } from '../../../core/money.ts'
import type { Payout } from '../../../db/commerce-schema.ts'
import { toTimestamp } from '../../../db/timestamp.ts'

const filterQuery = z.object({ seller: z.string().optional() })
const runForm = z.object({ as_of: z.string().optional() })
const DAY_PATTERN = /^\d{4}-\d{2}-\d{2}$/

export const payoutRoutes: FastifyPluginCallback = (admin, _options, done) => {
  admin.get('/payouts', async (request, reply) => {
    const { seller } = filterQuery.parse(request.query)
    const sellerId = parseSellerId(seller)
    const context = { db: admin.db }

    return reply.render(
      'payouts',
      adminPage('Payouts', {
        rows: await payoutRows(context, { sellerId }),
        sellers: await sellerOptions(context),
        selectedSeller: sellerId,
        today: toTimestamp(admin.clock.now()),
      }),
    )
  })

  admin.post('/payouts', async (request, reply) => {
    const asOf = parseAsOf(runForm.parse(request.body).as_of, admin.clock.now())
    const payouts = await runWeeklyPayout({ db: admin.db, clock: admin.clock }, asOf)

    reply.setFlash({ notice: payoutFlashMessage(payouts, payoutPeriodEndingBefore(asOf)) })

    return reply.redirect('/admin/payouts')
  })

  done()
}

function parseSellerId(value: string | undefined): number | undefined {
  if (value === undefined || value === '') return undefined

  const id = Number(value)

  return Number.isInteger(id) && id > 0 ? id : undefined
}

/**
 * `parseAsOf` in `app/cli/parse-as-of.ts` reads a `--as-of=` command-line flag
 * out of an argv array, which is not the shape a form post hands a route, so
 * this parses the submitted day directly instead of reusing it.
 */
function parseAsOf(value: string | undefined, fallback: Date): Date {
  return value !== undefined && DAY_PATTERN.test(value) ? new Date(`${value}T00:00:00.000Z`) : fallback
}

function payoutFlashMessage(payouts: readonly Payout[], period: PayoutPeriod): string {
  const periodLabel = payoutPeriodLabel(period)
  if (payouts.length === 0) return `No seller had a released balance to pay for ${periodLabel}.`

  const total = payouts.reduce((sum, payout) => addCents(sum, payout.amountCents), 0)

  return `Paid ${payouts.length} seller(s) ${formatCents(total)} for ${periodLabel}.`
}
