require "test_helper"

module Domain
  module Reports
    class ActivityTimelineTest < ActiveSupport::TestCase
      test "it returns one row per day oldest first" do
        days = ActivityTimeline.last_days({}, ends_on: Time.new(2026, 8, 22, 17, 30, 0), days: 3)

        assert_equal 3, days.length
        assert_equal [Date.new(2026, 8, 20), Date.new(2026, 8, 21), Date.new(2026, 8, 22)], days.map(&:date)
      end

      test "it reads the counts recorded for a day" do
        counts = { Date.new(2026, 8, 21) => { "view" => 4, "favorite" => 1, "cart_add" => 2 } }

        days = ActivityTimeline.last_days(counts, ends_on: Date.new(2026, 8, 22), days: 2)

        assert_equal 4, days[0].totals.views
        assert_equal 1, days[0].totals.favorites
        assert_equal 2, days[0].totals.cart_adds
      end

      test "a day with no events counts zero" do
        counts = { Date.new(2026, 8, 22) => { "view" => 7 } }

        days = ActivityTimeline.last_days(counts, ends_on: Date.new(2026, 8, 22), days: 2)

        assert_equal 0, days[0].totals.total
        assert_equal 7, days[1].totals.views
      end

      test "it ignores counts outside the window" do
        counts = { Date.new(2026, 7, 1) => { "view" => 99 } }

        days = ActivityTimeline.last_days(counts, ends_on: Date.new(2026, 8, 22), days: 2)

        assert_equal 0, days.sum { |day| day.totals.total }
      end

      test "a fortnight ends on the day asked for" do
        days = ActivityTimeline.last_days({}, ends_on: Date.new(2026, 8, 22), days: 14)

        assert_equal Date.new(2026, 8, 9), days.first.date
        assert_equal Date.new(2026, 8, 22), days.last.date
      end

      test "it refuses a window shorter than a day" do
        assert_raises(ArgumentError) { ActivityTimeline.last_days({}, ends_on: Date.new(2026, 8, 22), days: 0) }
      end
    end
  end
end
