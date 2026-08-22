# Runs without Rails: ruby -Iapp app/domain/reports/daily_activity_test.rb
require "minitest/autorun"
require_relative "daily_activity"

module Domain
  module Reports
    class DailyActivityTest < Minitest::Test
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
