class LedgerEntry < ApplicationRecord
  # What a seller's ledger adds up to: money waiting on delivery, money ready
  # for the next payout, and money already sent.
  Balance = Data.define(:held, :available, :paid_out) do
    def self.from(totals)
      held = totals.fetch("held", 0)
      released = totals.fetch("released", 0)
      paid_out = totals.fetch("paid_out", 0)

      new(
        held: Domain::Money.from_cents(held - released),
        available: Domain::Money.from_cents(released + paid_out),
        paid_out: Domain::Money.from_cents(-paid_out)
      )
    end

    def payable?
      available.cents.positive?
    end
  end

  belongs_to :seller
  belongs_to :fulfillment, optional: true
  belongs_to :payout, optional: true

  enum :entry_type, { held: "held", released: "released", paid_out: "paid_out" }

  scope :occurred_by, ->(moment) { where(occurred_at: ..moment) }

  # amount_cents is signed: a hold and a release are positive and a payout is
  # negative, which is what lets a balance fold the whole ledger by adding.
  def self.hold(fulfillment, at:)
    create!(
      fulfillment: fulfillment, seller_id: fulfillment.seller_id,
      entry_type: :held, amount_cents: fulfillment.net_cents, occurred_at: at
    )
  end

  def self.release(fulfillment, at:)
    create!(
      fulfillment: fulfillment, seller_id: fulfillment.seller_id,
      entry_type: :released, amount_cents: fulfillment.net_cents, occurred_at: at
    )
  end

  def self.pay_out(payout, at:)
    create!(
      payout: payout, seller_id: payout.seller_id,
      entry_type: :paid_out, amount_cents: -payout.amount_cents, occurred_at: at
    )
  end

  def self.balance
    Balance.from(group(:entry_type).sum(:amount_cents))
  end

  # Every seller's balance from one grouped query, in seller id order.
  def self.balances_by_seller
    totals = Hash.new { |sellers, seller_id| sellers[seller_id] = {} }
    group(:seller_id, :entry_type).sum(:amount_cents).each do |(seller_id, entry_type), cents|
      totals[seller_id][entry_type] = cents
    end

    totals.sort.to_h.transform_values { |seller_totals| Balance.from(seller_totals) }
  end
end
