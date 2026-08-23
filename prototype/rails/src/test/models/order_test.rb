require "test_helper"

class OrderTest < ActiveSupport::TestCase
  test "an order totals in money" do
    order = order_for(create_verified_customer, create_listing(price_cents: 45_000))

    assert_equal "$450.00", order.total.format
  end
end
