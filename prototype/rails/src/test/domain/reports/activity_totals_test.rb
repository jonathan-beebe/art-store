require "test_helper"

module Domain
  module Reports
    class ActivityTotalsTest < ActiveSupport::TestCase
      test "it reads the count of each event the report shows" do
        totals = ActivityTotals.from({ "view" => 12, "favorite" => 3, "cart_add" => 2 })

        assert_equal 12, totals.views
        assert_equal 3, totals.favorites
        assert_equal 2, totals.cart_adds
      end

      test "an event that has not happened counts zero" do
        totals = ActivityTotals.from({ "view" => 12 })

        assert_equal 0, totals.favorites
        assert_equal 0, totals.cart_adds
      end

      test "it ignores events no report shows" do
        assert_equal 0, ActivityTotals.from({ "unfavorite" => 5 }).total
      end

      test "it sums the three event kinds" do
        assert_equal 17, ActivityTotals.from({ "view" => 12, "favorite" => 3, "cart_add" => 2 }).total
      end

      test "a listing nobody has seen totals zero" do
        assert_equal 0, ActivityTotals.from({}).total
      end
    end
  end
end
