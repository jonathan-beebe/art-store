class LedgerEntry < ApplicationRecord
  prefixed_id :led

  # What a seller's ledger adds up to: money waiting on delivery, money ready
  # for the next payout, and money already sent.
  Balance = Data.define(:held, :available, :paid_out) do
    def self.zero
      fold({})
    end

    # Totals keyed by [fulfillment_id, entry_type]. The ledger folds one
    # fulfillment at a time, so a refund lands where that fulfillment's money
    # stands. A payout names no fulfillment and folds under the same rule.
    def self.fold(totals)
      parts = totals.group_by { |(fulfillment_id, _entry_type), _cents| fulfillment_id }
                    .values
                    .map { |rows| part(rows.to_h { |(_id, entry_type), cents| [ entry_type, cents ] }) }

      new(
        held: Money.from_cents(parts.sum { |part| part[:held] }),
        available: Money.from_cents(parts.sum { |part| part[:available] }),
        paid_out: Money.from_cents(parts.sum { |part| part[:paid_out] })
      )
    end

    # What one fulfillment's entries add to each of the three balances. A
    # refund reverses the hold on a fulfillment nothing has released, and
    # comes out of what is available on one already released — which is what
    # carries a seller's balance negative until the next payout nets it.
    private_class_method def self.part(entries)
      held = entries.fetch("held", 0)
      released = entries.fetch("released", 0)
      paid_out = entries.fetch("paid_out", 0)
      refunded = entries.fetch("refunded", 0)
      still_held = released.zero?

      {
        held: held - released + (still_held ? refunded : 0),
        available: released + paid_out + (still_held ? 0 : refunded),
        paid_out: -paid_out
      }
    end

    def payable?
      available.cents.positive?
    end
  end

  belongs_to :seller
  belongs_to :fulfillment, optional: true
  belongs_to :payout, optional: true

  enum :entry_type, { held: "held", released: "released", paid_out: "paid_out", refunded: "refunded" }

  scope :occurred_by, ->(moment) { where(occurred_at: ..moment) }

  # amount_cents is signed: a hold and a release are positive and a payout is
  # negative, which is what lets a balance fold the whole ledger by adding.
  def self.hold(fulfillment, at:)
    write(
      fulfillment: fulfillment, seller_id: fulfillment.seller_id,
      entry_type: :held, amount_cents: fulfillment.net_cents, occurred_at: at
    )
  end

  def self.release(fulfillment, at:)
    write(
      fulfillment: fulfillment, seller_id: fulfillment.seller_id,
      entry_type: :released, amount_cents: fulfillment.net_cents, occurred_at: at
    )
  end

  # The money for a fulfillment goes back to the customer, so it leaves the
  # seller: the entry is the negative of the net the sale held for them.
  def self.refund(fulfillment, at:)
    write(
      fulfillment: fulfillment, seller_id: fulfillment.seller_id,
      entry_type: :refunded, amount_cents: -fulfillment.net_cents, occurred_at: at
    )
  end

  def self.pay_out(payout, at:)
    write(
      payout: payout, seller_id: payout.seller_id,
      entry_type: :paid_out, amount_cents: -payout.amount_cents, occurred_at: at
    )
  end

  # Every movement of money leaves a line behind it, at debug: a ledger read
  # back from the log is the same fold the balance is.
  private_class_method def self.write(attributes)
    Story.tell("ledger.write", "writing a #{attributes[:entry_type]} entry", level: :debug,
      seller_id: attributes[:seller_id], entry_type: attributes[:entry_type].to_s,
      amount_cents: attributes[:amount_cents]) do |story|
      entry = create!(attributes)

      story.did("wrote a #{entry.entry_type} entry", ledger_entry_id: entry.id,
        seller_id: entry.seller_id, entry_type: entry.entry_type, amount_cents: entry.amount_cents)

      entry
    end
  end

  def self.balance
    Balance.fold(group(:fulfillment_id, :entry_type).sum(:amount_cents))
  end

  # Every seller's balance from one grouped query, in seller id order.
  def self.balances_by_seller
    totals = Hash.new { |sellers, seller_id| sellers[seller_id] = {} }
    group(:seller_id, :fulfillment_id, :entry_type).sum(:amount_cents).each do |(seller_id, fulfillment_id, entry_type), cents|
      totals[seller_id][[ fulfillment_id, entry_type ]] = cents
    end

    totals.sort.to_h.transform_values { |seller_totals| Balance.fold(seller_totals) }
  end
end
