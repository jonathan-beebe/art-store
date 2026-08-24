class Payout < ApplicationRecord
  prefixed_id :pyt

  belongs_to :seller
  has_many :ledger_entries, dependent: :nullify

  # The weekly settlement: one row per seller holding money released and not
  # yet sent, for the Monday-to-Sunday week that ended before as_of.
  def self.run_weekly(as_of: Time.current)
    period = PayoutPeriod.ending_before(as_of)

    transaction do
      LedgerEntry.occurred_by(period.ends_at).balances_by_seller
                 .select { |_seller_id, balance| balance.payable? }
                 .map { |seller_id, balance| settle(seller_id, balance.available, period, as_of) }
    end
  end

  private_class_method def self.settle(seller_id, available, period, as_of)
    payout = create!(
      seller_id: seller_id,
      period_start: period.first_day, period_end: period.last_day,
      amount_cents: available.cents, paid_at: as_of
    )

    # Dated at the close of the period it settles, so a second run of the same
    # period already counts the money as sent and pays nothing.
    LedgerEntry.pay_out(payout, at: period.ends_at)

    payout
  end

  def amount
    Money.from_cents(amount_cents)
  end
end
