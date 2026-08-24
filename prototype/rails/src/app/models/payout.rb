class Payout < ApplicationRecord
  prefixed_id :pyt

  belongs_to :seller
  has_many :ledger_entries, dependent: :nullify

  scope :for_seller, ->(seller_id) { where(seller_id: seller_id) if seller_id.present? }

  # The weekly settlement: one row per seller holding money released and not
  # yet sent, for the Monday-to-Sunday week that ended before as_of.
  def self.run_weekly(as_of: Time.current)
    period = PayoutPeriod.ending_before(as_of)

    Story.tell("payout.run", "settling the week that ended #{period.last_day}",
      period_start: period.first_day.to_s, period_end: period.last_day.to_s) do |story|
      payouts = transaction do
        LedgerEntry.occurred_by(period.ends_at).balances_by_seller
                   .select { |_seller_id, balance| balance.payable? }
                   .map { |seller_id, balance| settle(seller_id, balance.available, period, as_of) }
      end

      story.did("settled the week that ended #{period.last_day}",
        period_start: period.first_day.to_s, period_end: period.last_day.to_s,
        payout_count: payouts.size, total_cents: payouts.sum(&:amount_cents))

      payouts
    end
  end

  private_class_method def self.settle(seller_id, available, period, as_of)
    Story.tell("payout.pay", "paying a seller what the week released",
      seller_id: seller_id, amount_cents: available.cents) do |story|
      payout = pay(seller_id, available, period, as_of)

      story.did("paid the seller", seller_id: seller_id, payout_id: payout.id, amount_cents: payout.amount_cents)

      payout
    end
  end

  private_class_method def self.pay(seller_id, available, period, as_of)
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
