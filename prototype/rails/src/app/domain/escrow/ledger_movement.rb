require_relative "../money"
require_relative "ledger_entry_type"

module Domain
  module Escrow
    # One signed step through escrow. Holds and releases are positive; a payout
    # is negative, which is what lets a balance fold the whole ledger by adding.
    LedgerMovement = Data.define(:entry_type, :amount) do
      def self.hold(net)
        new(entry_type: LedgerEntryType::HELD, amount: net)
      end

      def self.release(net)
        new(entry_type: LedgerEntryType::RELEASED, amount: net)
      end

      def self.payout(available)
        new(entry_type: LedgerEntryType::PAID_OUT, amount: Money.from_cents(-available.cents))
      end
    end
  end
end
