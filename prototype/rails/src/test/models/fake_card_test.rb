require "test_helper"

class FakeCardTest < ActiveSupport::TestCase
  test "the approved number is approved" do
    assert_predicate FakeCard.new("4242424242424242"), :approved?
  end

  test "spaces and dashes are ignored" do
    assert_predicate FakeCard.new("4242 4242-4242 4242"), :approved?
  end

  test "an approved card carries no decline reason" do
    assert_nil FakeCard.new("4242424242424242").decline_reason
  end

  test "the generic decline number is declined" do
    card = FakeCard.new("4000 0000 0000 0002")

    refute_predicate card, :approved?
    assert_equal "generic_decline", card.decline_reason
  end

  test "the insufficient funds number is declined" do
    assert_equal "insufficient_funds", FakeCard.new("4000 0000 0000 9995").decline_reason
  end

  test "any other number is not a valid card" do
    assert_equal "invalid_card_number", FakeCard.new("1234 5678 1234 5678").decline_reason
  end

  test "only the last four digits come back" do
    assert_equal "9995", FakeCard.new("4000 0000 0000 9995").last_four
  end

  test "a number shorter than four digits keeps what it has" do
    assert_equal "12", FakeCard.new("12").last_four
  end
end
