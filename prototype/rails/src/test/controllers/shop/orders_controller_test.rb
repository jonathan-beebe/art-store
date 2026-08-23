require "test_helper"

module Shop
  class OrdersControllerTest < ActionDispatch::IntegrationTest
    test "it lists the orders of the visitor" do
      order = paid_order

      get shop_orders_path

      assert_response :success
      assert_select "a", text: "Order ##{order.id}"
      assert_select "p", text: "Harbour at Dusk"
      assert_select "p", text: "Paid"
    end

    test "an order belonging to someone else is not listed" do
      paid_order
      end_session
      sign_in_as_customer(email: "stranger@example.com")

      get shop_orders_path

      assert_select "p", text: "No orders yet."
    end

    test "it groups the items by seller with their fulfillment status" do
      kiln = create_seller(shop_name: "Blue Kiln Studio")
      press = create_seller(shop_name: "North Press")
      order = paid_order(
        create_listing(kiln, title: "Harbour at Dusk"),
        create_listing(press, title: "Winter Field", price_cents: 12_000)
      )

      get shop_order_path(order)

      assert_response :success
      assert_select "h2", text: "Blue Kiln Studio"
      assert_select "h2", text: "North Press"
      assert_select "[data-fulfillment-status]", text: "Awaiting shipment", count: 2
      assert_select "span", text: "Harbour at Dusk × 1"
      assert_select "address", text: /12 Analytical Way/
    end

    test "it shows the carrier and tracking once shipped" do
      order = paid_order
      ship(order.fulfillments.sole)

      get shop_order_path(order)

      assert_select "[data-tracking]", text: /Royal Mail · tracking RM123/
      assert_select "[data-order-status]", text: "Shipped"
      assert_select "button", text: "Confirm delivery"
    end

    test "it offers no delivery confirmation before shipping" do
      order = paid_order

      get shop_order_path(order)

      assert_select "button", text: "Confirm delivery", count: 0
    end

    test "another customer cannot read the order" do
      order = paid_order
      end_session
      sign_in_as_customer(email: "stranger@example.com")

      get shop_order_path(order)

      assert_response :not_found
    end

    test "an empty order list says so" do
      get shop_orders_path

      assert_select "p", text: "No orders yet."
    end

    private

    def paid_order(*listings)
      listings = [create_listing(title: "Harbour at Dusk")] if listings.empty?
      sign_in_as_customer(email: "buyer@example.com")
      listings.each { |listing| post shop_add_to_cart_path(slug: listing.slug) }
      post shop_place_order_path,
        params: { email: "buyer@example.com", card_number: APPROVED_CARD }.merge(shipping_params)

      order_of_visiting_customer
    end

    def ship(fulfillment)
      Fulfillments::MarkShipped.new.call(
        fulfillment: fulfillment, carrier: "Royal Mail", tracking_number: "RM123",
        now: Time.zone.parse("2026-08-21 09:00:00")
      )
    end
  end
end
