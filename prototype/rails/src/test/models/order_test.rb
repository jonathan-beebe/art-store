require "commerce_test_case"

class OrderTest < CommerceTestCase
  test "an order totals in money" do
    order = order_for(customer, listing(seller, price_cents: 45_000))

    assert_equal "$450.00", order.total.format
  end
end
