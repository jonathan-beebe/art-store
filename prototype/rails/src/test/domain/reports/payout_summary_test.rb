require "test_helper"

module Domain
  module Reports
    class PayoutSummaryTest < ActiveSupport::TestCase
      test "it counts the payouts a run wrote" do
        assert_equal 2, PayoutSummary.of([Money.from_cents(9_000), Money.from_cents(4_500)]).count
      end

      test "it totals the amounts paid out" do
        summary = PayoutSummary.of([Money.from_cents(9_000), Money.from_cents(4_500)])

        assert_equal "$135.00", summary.total.format
      end

      test "a run that paid nobody comes to nothing" do
        summary = PayoutSummary.of([])

        assert_equal 0, summary.count
        assert_equal "$0.00", summary.total.format
      end
    end
  end
end
