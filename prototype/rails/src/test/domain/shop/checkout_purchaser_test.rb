require "test_helper"

class CheckoutPurchaserTest < ActiveSupport::TestCase
  CheckoutPurchaser = Domain::Shop::CheckoutPurchaser

  test "a guest buys under the address they typed" do
    purchaser = CheckoutPurchaser.for_checkout(
      id: 7, account_email: nil, account_verified_at: nil, submitted_email: "  Ada@Example.Test "
    )

    assert_equal 7, purchaser.id
    assert_equal "ada@example.test", purchaser.email
    refute purchaser.email_verified?
  end

  test "a signed in customer buys under the address on their account" do
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
