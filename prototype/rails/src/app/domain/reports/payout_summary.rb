require_relative "../money"

module Domain
  module Reports
    # What one payout run came to, for the line the portal reports back.
    PayoutSummary = Data.define(:count, :total) do
      def self.of(amounts)
        new(count: amounts.length, total: amounts.sum(Money.from_cents(0)))
      end
    end
  end
end
