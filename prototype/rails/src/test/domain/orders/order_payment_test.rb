require "test_helper"

class OrderPaymentTest < ActiveSupport::TestCase
  Payment = Domain::Orders::OrderPayment
  Status = Domain::Orders::OrderStatus

  test "an order awaiting payment takes a card" do
    assert Payment.awaits_card?(Status::AWAITING_PAYMENT)
  end

  test "a declined order takes another card" do
    assert Payment.awaits_card?(Status::PAYMENT_FAILED)
  end

  test "an unverified order takes no card yet" do
    refute Payment.awaits_card?(Status::PENDING_VERIFICATION)
  end

  test "a paid order takes no card" do
    refute Payment.awaits_card?(Status::PAID)
  end

  test "an unverified order is unpaid" do
    assert Payment.unpaid?(Status::PENDING_VERIFICATION)
  end

  test "a declined order is unpaid" do
    assert Payment.unpaid?(Status::PAYMENT_FAILED)
  end

  test "a shipped order is not unpaid" do
    refute Payment.unpaid?(Status::SHIPPED)
  end

  test "a verified purchaser pays an order awaiting payment" do
    assert Payment.payable?(Status::AWAITING_PAYMENT, true)
  end

  test "an unverified purchaser pays nothing" do
    refute Payment.payable?(Status::AWAITING_PAYMENT, false)
  end

  test "a verified purchaser cannot pay a delivered order" do
    refute Payment.payable?(Status::DELIVERED, true)
  end
end
