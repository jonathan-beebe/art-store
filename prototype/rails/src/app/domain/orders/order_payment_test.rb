require "minitest/autorun"
require_relative "order_payment"

class OrderPaymentTest < Minitest::Test
  Payment = Domain::Orders::OrderPayment
  Status = Domain::Orders::OrderStatus

  def test_an_order_awaiting_payment_takes_a_card
    assert Payment.awaits_card?(Status::AWAITING_PAYMENT)
  end

  def test_a_declined_order_takes_another_card
    assert Payment.awaits_card?(Status::PAYMENT_FAILED)
  end

  def test_an_unverified_order_takes_no_card_yet
    refute Payment.awaits_card?(Status::PENDING_VERIFICATION)
  end

  def test_a_paid_order_takes_no_card
    refute Payment.awaits_card?(Status::PAID)
  end

  def test_an_unverified_order_is_unpaid
    assert Payment.unpaid?(Status::PENDING_VERIFICATION)
  end

  def test_a_declined_order_is_unpaid
    assert Payment.unpaid?(Status::PAYMENT_FAILED)
  end

  def test_a_shipped_order_is_not_unpaid
    refute Payment.unpaid?(Status::SHIPPED)
  end

  def test_a_verified_purchaser_pays_an_order_awaiting_payment
    assert Payment.payable?(Status::AWAITING_PAYMENT, true)
  end

  def test_an_unverified_purchaser_pays_nothing
    refute Payment.payable?(Status::AWAITING_PAYMENT, false)
  end

  def test_a_verified_purchaser_cannot_pay_a_delivered_order
    refute Payment.payable?(Status::DELIVERED, true)
  end
end
