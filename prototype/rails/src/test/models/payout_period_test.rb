require "test_helper"

class PayoutPeriodTest < ActiveSupport::TestCase
  test "a monday settles the week that just ended" do
    period = PayoutPeriod.ending_before(Time.utc(2026, 8, 24, 9, 0, 0))

    assert_equal Date.new(2026, 8, 17), period.first_day
    assert_equal Date.new(2026, 8, 23), period.last_day
  end

  test "every day of a week settles the same period" do
    period = PayoutPeriod.ending_before(Time.utc(2026, 8, 28, 23, 0, 0))

    assert_equal Date.new(2026, 8, 17), period.first_day
    assert_equal Date.new(2026, 8, 23), period.last_day
  end

  test "a sunday still belongs to the week it is closing" do
    period = PayoutPeriod.ending_before(Time.utc(2026, 8, 30, 9, 0, 0))

    assert_equal Date.new(2026, 8, 17), period.first_day
    assert_equal Date.new(2026, 8, 23), period.last_day
  end

  test "it takes a date as readily as a time" do
    assert_equal Date.new(2026, 8, 17), PayoutPeriod.ending_before(Date.new(2026, 8, 24)).first_day
  end

  test "the period ends with the last second of its last day" do
    period = PayoutPeriod.ending_before(Time.utc(2026, 8, 24, 9, 0, 0))

    assert_equal Time.utc(2026, 8, 23, 23, 59, 59), period.ends_at
  end

  test "it labels itself with both ends" do
    assert_equal "2026-08-17 to 2026-08-23", PayoutPeriod.ending_before(Time.utc(2026, 8, 24)).label
  end
end
