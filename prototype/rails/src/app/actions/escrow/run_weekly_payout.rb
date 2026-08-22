module Escrow
  class RunWeeklyPayout
    def call(as_of:)
      period = Domain::Escrow::PayoutPeriod.ending_before(as_of)

      ActiveRecord::Base.transaction do
        payable_balances(period).map { |seller_id, balance| pay_out(seller_id, balance.available, period, as_of) }
      end
    end

    private

    def payable_balances(period)
      LedgerEntry.occurred_by(period.ends_at)
                 .order(:seller_id, :id)
                 .group_by(&:seller_id)
                 .transform_values { |entries| Domain::Escrow::LedgerBalance.from(entries.map(&:to_movement)) }
                 .select { |_seller_id, balance| balance.payable? }
    end

    def pay_out(seller_id, available, period, as_of)
      payout = Payout.create!(
        seller_id: seller_id,
        period_start: period.first_day,
        period_end: period.last_day,
        amount_cents: available.cents,
        paid_at: as_of
      )
      movement = Domain::Escrow::LedgerMovement.payout(available)

      # Dated at the close of the period it settles, so a second run of the same
      # period already counts the money as sent and pays nothing.
      payout.ledger_entries.create!(
        seller_id: seller_id,
        entry_type: movement.entry_type,
        amount_cents: movement.amount.cents,
        occurred_at: period.ends_at
      )

      payout
    end
  end
end
