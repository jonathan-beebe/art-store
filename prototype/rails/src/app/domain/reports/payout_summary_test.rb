# Runs without Rails: ruby -Iapp app/domain/reports/payout_summary_test.rb
require "minitest/autorun"
require_relative "payout_summary"

module Domain
  module Reports
    class PayoutSummaryTest < Minitest::Test
      def test_it_counts_the_payouts_a_run_wrote
        assert_equal 2, PayoutSummary.of([Money.from_cents(9_000), Money.from_cents(4_500)]).count
      end

      def test_it_totals_the_amounts_paid_out
        summary = PayoutSummary.of([Money.from_cents(9_000), Money.from_cents(4_500)])

        assert_equal "$135.00", summary.total.format
      end

      def test_a_run_that_paid_nobody_comes_to_nothing
        summary = PayoutSummary.of([])

        assert_equal 0, summary.count
        assert_equal "$0.00", summary.total.format
      end
    end
  end
end
