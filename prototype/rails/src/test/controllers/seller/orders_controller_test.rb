require "test_helper"

class Seller::OrdersControllerTest < ActionDispatch::IntegrationTest
  test "a signed-out visitor reaches no order page" do
    fulfillment = create_fulfillment(other_seller)

    get seller_orders_path
    assert_redirected_to seller_login_path

    get seller_order_path(fulfillment)
    assert_redirected_to seller_login_path
  end

  test "the index groups the seller's fulfillments by status" do
    seller = signed_in_seller
    waiting = create_fulfillment(seller)
    shipped = create_delivered_fulfillment(seller)

    get seller_orders_path

    assert_response :success
    assert_select "[data-group=awaiting_shipment]" do
      assert_select "h2", text: "Awaiting shipment (1)"
      assert_select "[data-fulfillment=?]", waiting.id.to_s
    end
    assert_select "[data-group=delivered]" do
      assert_select "[data-fulfillment=?]", shipped.id.to_s
    end
    assert_select "[data-group=shipped] p", text: "Nothing here."
  end

  test "another seller's fulfillments stay off the index" do
    signed_in_seller
    rival = create_fulfillment(other_seller)

    get seller_orders_path

    assert_select "[data-fulfillment=?]", rival.id.to_s, false
  end

  test "the order page shows the address, the seller's own items, and the net" do
    seller = signed_in_seller
    fulfillment = create_fulfillment(seller, listing: create_listing(seller, title: "Harbour at Dusk"))

    get seller_order_path(fulfillment)

    assert_response :success
    assert_select "[data-shipping-address]", text: /Ada Lovelace/
    assert_select "[data-shipping-address]", text: /12 Analytical Way/
    assert_select "[data-item] th", text: "Harbour at Dusk"
    assert_select "[data-cell=net]", text: "$405.00"
  end

  test "an order waiting on a shipment offers the mark-shipped form" do
    seller = signed_in_seller
    fulfillment = create_fulfillment(seller)

    get seller_order_path(fulfillment)

    assert_select "form[action=?][method=post]", seller_order_shipment_path(fulfillment)
    assert_select "label[for=carrier]"
    assert_select "label[for=tracking_number]"
  end

  test "a shipped order shows its carrier and timestamps in place of the form" do
    seller = signed_in_seller
    fulfillment = create_delivered_fulfillment(seller)

    get seller_order_path(fulfillment)

    assert_select "form[action=?]", seller_order_shipment_path(fulfillment), false
    assert_select "[data-cell=carrier]", text: "Royal Mail"
    assert_select "[data-cell=tracking_number]", text: "RM123"
    assert_select "[data-cell=shipped_at]"
    assert_select "[data-cell=delivered_at]"
  end

  test "another seller's order page is not found" do
    signed_in_seller

    get seller_order_path(create_fulfillment(other_seller))

    assert_response :not_found
  end
end
