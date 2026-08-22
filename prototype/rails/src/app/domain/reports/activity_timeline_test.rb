# Runs without Rails: ruby -Iapp app/domain/reports/activity_timeline_test.rb
require "minitest/autorun"
require_relative "activity_timeline"

module Domain
  module Reports
    class ActivityTimelineTest < Minitest::Test
      def test_it_returns_one_row_per_day_oldest_first
        days = ActivityTimeline.last_days({}, ends_on: Time.new(2026, 8, 22, 17, 30, 0), days: 3)

        assert_equal 3, days.length
        assert_equal [Date.new(2026, 8, 20), Date.new(2026, 8, 21), Date.new(2026, 8, 22)], days.map(&:date)
      end

      def test_it_reads_the_counts_recorded_for_a_day
        counts = { Date.new(2026, 8, 21) => { "view" => 4, "favorite" => 1, "cart_add" => 2 } }

        days = ActivityTimeline.last_days(counts, ends_on: Date.new(2026, 8, 22), days: 2)

        assert_equal 4, days[0].totals.views
        assert_equal 1, days[0].totals.favorites
        assert_equal 2, days[0].totals.cart_adds
      end

      def test_a_day_with_no_events_counts_zero
        counts = { Date.new(2026, 8, 22) => { "view" => 7 } }

        days = ActivityTimeline.last_days(counts, ends_on: Date.new(2026, 8, 22), days: 2)

        assert_equal 0, days[0].totals.total
        assert_equal 7, days[1].totals.views
      end

      def test_it_ignores_counts_outside_the_window
        counts = { Date.new(2026, 7, 1) => { "view" => 99 } }

        days = ActivityTimeline.last_days(counts, ends_on: Date.new(2026, 8, 22), days: 2)

        assert_equal 0, days.sum { |day| day.totals.total }
      end

      def test_a_fortnight_ends_on_the_day_asked_for
        days = ActivityTimeline.last_days({}, ends_on: Date.new(2026, 8, 22), days: 14)

        assert_equal Date.new(2026, 8, 9), days.first.date
        assert_equal Date.new(2026, 8, 22), days.last.date
      end

      def test_it_refuses_a_window_shorter_than_a_day
        assert_raises(ArgumentError) { ActivityTimeline.last_days({}, ends_on: Date.new(2026, 8, 22), days: 0) }
      end
    end
  end
end
