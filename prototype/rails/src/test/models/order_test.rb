require "commerce_test_case"

class OrderTest < CommerceTestCase
  def test_an_order_totals_in_money
    order = order_for(customer, listing(seller, price_cents: 45_000))

    assert_equal "$450.00", order.total.format
  end
end
