require "test_helper"

module Domain
  module Escrow
    class PayoutPeriodTest < ActiveSupport::TestCase
      def test_a_monday_settles_the_week_that_just_ended
        period = PayoutPeriod.ending_before(Time.utc(2026, 8, 24, 9, 0, 0))

        assert_equal Date.new(2026, 8, 17), period.first_day
        assert_equal Date.new(2026, 8, 23), period.last_day
      end

      def test_every_day_of_a_week_settles_the_same_period
        period = PayoutPeriod.ending_before(Time.utc(2026, 8, 28, 23, 0, 0))

        assert_equal Date.new(2026, 8, 17), period.first_day
        assert_equal Date.new(2026, 8, 23), period.last_day
      end

      def test_a_sunday_still_belongs_to_the_week_it_is_closing
        period = PayoutPeriod.ending_before(Time.utc(2026, 8, 30, 9, 0, 0))

        assert_equal Date.new(2026, 8, 17), period.first_day
        assert_equal Date.new(2026, 8, 23), period.last_day
      end

      def test_it_takes_a_date_as_readily_as_a_time
        assert_equal Date.new(2026, 8, 17), PayoutPeriod.ending_before(Date.new(2026, 8, 24)).first_day
      end

      def test_the_period_ends_with_the_last_second_of_its_last_day
        period = PayoutPeriod.ending_before(Time.utc(2026, 8, 24, 9, 0, 0))

        assert_equal Time.utc(2026, 8, 23, 23, 59, 59), period.ends_at
      end

      def test_it_covers_a_moment_inside_it
        period = PayoutPeriod.ending_before(Time.utc(2026, 8, 24, 9, 0, 0))

        assert period.covers?(Time.utc(2026, 8, 21, 11, 0, 0))
      end

      def test_it_does_not_cover_a_moment_after_it
        period = PayoutPeriod.ending_before(Time.utc(2026, 8, 24, 9, 0, 0))

        refute period.covers?(Time.utc(2026, 8, 24, 0, 0, 1))
      end

      def test_it_labels_itself_with_both_ends
        assert_equal "2026-08-17 to 2026-08-23", PayoutPeriod.ending_before(Time.utc(2026, 8, 24)).label
      end
    end
  end
end
