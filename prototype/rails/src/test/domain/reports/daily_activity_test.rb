require "test_helper"

module Domain
  module Reports
    class DailyActivityTest < ActiveSupport::TestCase
      test "it labels the day for a table row" do
        day = DailyActivity.new(date: Date.new(2026, 8, 9), totals: ActivityTotals.from({}))

        assert_equal "Aug 9", day.label
      end

      test "it carries the totals of its day" do
        day = DailyActivity.new(date: Date.new(2026, 8, 9), totals: ActivityTotals.from({ "view" => 3 }))

        assert_equal 3, day.totals.views
      end
    end
  end
end
