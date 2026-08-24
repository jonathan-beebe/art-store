# One seller's reconciliation line on /admin/accounting: their escrow
# balance beside what the platform earned and forgave in fees on their sales,
# and what went back to their customers.
SellerAccount = Data.define(:seller, :held, :available, :paid_out, :fees_earned, :fees_refunded, :refunded) do
  delegate :id, :display_name, :email, to: :seller

  # Every seller, reconciled from four grouped reads of the whole platform —
  # never one query per seller, so the page costs the same for one seller as
  # for a hundred.
  def self.for_every_seller
    balances = LedgerEntry.balances_by_seller
    fees_earned = Fulfillment.settled.live.group(:seller_id).sum(:fee_cents)
    fees_refunded = Fulfillment.settled.reversed.group(:seller_id).sum(:fee_cents)
    refunded = Refund.joins(:fulfillment).group("fulfillments.seller_id").sum(:amount_cents)

    Seller.order(:created_at, :id).map do |seller|
      balance = balances.fetch(seller.id, LedgerEntry::Balance.zero)

      new(
        seller: seller,
        held: balance.held,
        available: balance.available,
        paid_out: balance.paid_out,
        fees_earned: Money.from_cents(fees_earned.fetch(seller.id, 0)),
        fees_refunded: Money.from_cents(fees_refunded.fetch(seller.id, 0)),
        refunded: Money.from_cents(refunded.fetch(seller.id, 0))
      )
    end
  end
end
