require "minitest/autorun"
require "time"
require_relative "checkout_purchaser"

class CheckoutPurchaserTest < Minitest::Test
  CheckoutPurchaser = Domain::Shop::CheckoutPurchaser

  def test_a_guest_buys_under_the_address_they_typed
    purchaser = CheckoutPurchaser.for_checkout(
      id: 7, account_email: nil, account_verified_at: nil, submitted_email: "  Ada@Example.Test "
    )

    assert_equal 7, purchaser.id
    assert_equal "ada@example.test", purchaser.email
    refute purchaser.email_verified?
  end

  def test_a_signed_in_customer_buys_under_the_address_on_their_account
    verified_at = Time.utc(2026, 8, 20, 9)

    purchaser = CheckoutPurchaser.for_checkout(
      id: 7, account_email: "ada@example.test", account_verified_at: verified_at,
      submitted_email: "someone-else@example.test"
    )

    assert_equal "ada@example.test", purchaser.email
    assert_equal verified_at, purchaser.email_verified_at
    assert purchaser.email_verified?
  end
end
