require_relative "../money"
require_relative "ledger_entry_type"

module Domain
  module Escrow
    # What a seller's ledger adds up to: money waiting on delivery, money ready
    # for the next payout, and money already sent.
    LedgerBalance = Data.define(:held, :available, :paid_out) do
      def self.from(movements)
        totals = movements.group_by(&:entry_type).transform_values { |own| own.sum { |movement| movement.amount.cents } }
        held = totals.fetch(LedgerEntryType::HELD, 0)
        released = totals.fetch(LedgerEntryType::RELEASED, 0)
        paid_out = totals.fetch(LedgerEntryType::PAID_OUT, 0)

        new(
          held: Money.from_cents(held - released),
          available: Money.from_cents(released + paid_out),
          paid_out: Money.from_cents(-paid_out)
        )
      end

      def payable?
        available.cents.positive?
      end
    end
  end
end
