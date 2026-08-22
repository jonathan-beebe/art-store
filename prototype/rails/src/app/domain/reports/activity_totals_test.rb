# Runs without Rails: ruby -Iapp app/domain/reports/activity_totals_test.rb
require "minitest/autorun"
require_relative "activity_totals"

module Domain
  module Reports
    class ActivityTotalsTest < Minitest::Test
      def test_it_reads_the_count_of_each_event_the_report_shows
        totals = ActivityTotals.from({ "view" => 12, "favorite" => 3, "cart_add" => 2 })

        assert_equal 12, totals.views
        assert_equal 3, totals.favorites
        assert_equal 2, totals.cart_adds
      end

      def test_an_event_that_has_not_happened_counts_zero
        totals = ActivityTotals.from({ "view" => 12 })

        assert_equal 0, totals.favorites
        assert_equal 0, totals.cart_adds
      end

      def test_it_ignores_events_no_report_shows
        assert_equal 0, ActivityTotals.from({ "unfavorite" => 5 }).total
      end

      def test_it_sums_the_three_event_kinds
        assert_equal 17, ActivityTotals.from({ "view" => 12, "favorite" => 3, "cart_add" => 2 }).total
      end

      def test_a_listing_nobody_has_seen_totals_zero
        assert_equal 0, ActivityTotals.from({}).total
      end
    end
  end
end
