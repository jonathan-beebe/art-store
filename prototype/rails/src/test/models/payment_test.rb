require "test_helper"

class PaymentTest < ActiveSupport::TestCase
  test "a payment is approved or declined" do
    assert_equal %w[approved declined], Payment.statuses.keys
  end

  test "every decline reason has a message for the customer" do
    assert_equal Payment.decline_reasons.keys.sort, Payment::DECLINE_MESSAGES.keys.sort
  end

  test "insufficient funds says so" do
    payment = Payment.new(decline_reason: "insufficient_funds")

    assert_equal "Your card has insufficient funds.", payment.decline_message
  end

  test "a payment that was not declined has nothing to say" do
    assert_raises(KeyError) { Payment.new.decline_message }
  end
end
