require "test_helper"

module Domain
  module Reports
    class DailyActivityTest < ActiveSupport::TestCase
      def test_it_labels_the_day_for_a_table_row
        day = DailyActivity.new(date: Date.new(2026, 8, 9), totals: ActivityTotals.from({}))

        assert_equal "Aug 9", day.label
      end

      def test_it_carries_the_totals_of_its_day
        day = DailyActivity.new(date: Date.new(2026, 8, 9), totals: ActivityTotals.from({ "view" => 3 }))

        assert_equal 3, day.totals.views
      end
    end
  end
end
