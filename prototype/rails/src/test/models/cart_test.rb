require "test_helper"

class CartTest < ActiveSupport::TestCase
  test "it puts the listing in the cart" do
    art = create_listing(quantity: 3)
    cart = cart_for(create_verified_customer)

    item = cart.add(art, quantity: 2, at: moment("2026-08-20 08:00:00"))

    assert_equal 2, item.quantity
    assert_equal [art], cart.reload.items.map(&:listing)
  end

  test "adding the same listing again adds to the line" do
    art = create_listing(quantity: 3)
    cart = cart_for(create_verified_customer)
    cart.add(art, at: moment("2026-08-20 08:00:00"))

    cart.add(art, at: moment("2026-08-20 08:05:00"))

    assert_equal 1, cart.reload.items.count
    assert_equal 2, cart.items.sole.quantity
  end

  test "a cart never holds more than the seller has left" do
    item = cart_for(create_verified_customer).add(create_listing(quantity: 2), quantity: 5)

    assert_equal 2, item.quantity
  end

  test "it refuses a sold out listing" do
    sold = create_listing(quantity: 0, status: "sold")

    assert_raises(ArgumentError) { cart_for(create_verified_customer).add(sold) }
  end

  test "it holds at least one of a listing" do
    assert_raises(ArgumentError) { cart_for(create_verified_customer).add(create_listing, quantity: 0) }
  end

  test "it records the interest against the listing" do
    art = create_listing
    buyer = create_verified_customer

    cart_for(buyer).add(art, at: moment("2026-08-20 08:00:00"))

    event = art.events.sole
    assert_equal "cart_add", event.event_type
    assert_equal buyer.id, event.customer_id
  end

  test "it takes the listing out of the cart" do
    shop = create_seller
    kept = create_listing(shop)
    dropped = create_listing(shop)
    cart = cart_holding(create_verified_customer, kept, dropped)

    cart.remove(dropped)

    assert_equal [kept], cart.reload.items.map(&:listing)
  end

  test "removing a listing the cart never held changes nothing" do
    cart = cart_holding(create_verified_customer, create_listing)

    cart.remove(create_listing)

    assert_equal 1, cart.reload.items.count
  end

  test "it counts every item" do
    cart = cart_for(create_verified_customer)
    cart.add(create_listing(quantity: 3), quantity: 2)
    cart.add(create_listing)

    assert_equal 3, cart.item_count
  end

  test "it adds every line" do
    shop = create_seller
    cart = cart_for(create_verified_customer)
    cart.add(create_listing(shop, price_cents: 45_000, quantity: 2), quantity: 2)
    cart.add(create_listing(create_seller, price_cents: 1_000))

    assert_equal 91_000, cart.subtotal.cents
  end

  test "it splits the subtotal by seller" do
    shop = create_seller
    rival = create_seller
    cart = cart_for(create_verified_customer)
    cart.add(create_listing(shop, price_cents: 45_000, quantity: 2), quantity: 2)
    cart.add(create_listing(shop, price_cents: 500))
    cart.add(create_listing(rival, price_cents: 1_000))

    assert_equal({ shop.id => 90_500, rival.id => 1_000 }, cart.subtotals_by_seller.transform_values(&:cents))
  end

  test "it orders the sellers by id" do
    first = create_seller
    second = create_seller
    cart = cart_for(create_verified_customer)
    cart.add(create_listing(second))
    cart.add(create_listing(first))

    assert_equal [first.id, second.id], cart.subtotals_by_seller.keys
  end

  test "an empty cart totals nothing" do
    cart = cart_for(create_verified_customer)

    assert_predicate cart, :empty?
    assert_equal 0, cart.item_count
    assert_equal 0, cart.subtotal.cents
    assert_empty cart.subtotals_by_seller
  end

  test "a cart holding a listing is not empty" do
    refute_predicate cart_holding(create_verified_customer, create_listing), :empty?
  end
end
