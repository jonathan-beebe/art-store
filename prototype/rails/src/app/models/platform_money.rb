# The platform's whole ledger balance beside what it earned and forgave in
# fees, and what went back to customers — the numbers /admin and
# /admin/accounting both show. Every figure is folded once, in a query
# whose cost does not grow with the number of sellers.
PlatformMoney = Data.define(:held, :available, :paid_out, :fees_earned, :fees_refunded, :refunded) do
  def self.fold
    balance = LedgerEntry.balance

    new(
      held: balance.held,
      available: balance.available,
      paid_out: balance.paid_out,
      fees_earned: Money.from_cents(Fulfillment.fees_earned_cents),
      fees_refunded: Money.from_cents(Fulfillment.fees_refunded_cents),
      refunded: Money.from_cents(Refund.sum(:amount_cents))
    )
  end
end
