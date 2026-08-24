require "test_helper"

module Shop
  class OrderPaymentsControllerTest < ActionDispatch::IntegrationTest
    test "it asks a signed-in customer for a card" do
      order = placed_order

      get shop_order_payment_path(order)

      assert_response :success
      assert_select "h1", text: "Pay for order #{order.id}"
      assert_select "input[name=card_number]"
    end

    test "it moves a verified guest order to awaiting payment" do
      order = placed_order

      get shop_order_payment_path(order)

      assert_equal "awaiting_payment", order.reload.status
    end

    test "it sends a returning customer without a session to sign in first" do
      order = placed_order
      end_session

      get shop_order_payment_path(order)

      assert_redirected_to customer_login_path(redirect_to: shop_order_payment_path(order))
      assert_equal "pending_verification", order.reload.status
    end

    test "another customer cannot read the pay page" do
      order = placed_order
      post customer_logout_path
      sign_in_as_customer(email: "stranger@example.com")

      get shop_order_payment_path(order)

      assert_response :not_found
    end

    test "another customer cannot pay the order" do
      order = placed_order
      post customer_logout_path
      sign_in_as_customer(email: "stranger@example.com")

      post shop_pay_order_path(order), params: { card_number: APPROVED_CARD }

      assert_response :not_found
      assert_equal "pending_verification", order.reload.status
    end

    test "a paid order goes back to the order page" do
      order = placed_order
      get shop_order_payment_path(order)
      post shop_pay_order_path(order), params: { card_number: APPROVED_CARD }

      get shop_order_payment_path(order)

      assert_redirected_to shop_order_path(order)
    end

    test "a paid order cannot be charged again" do
      order = placed_order
      get shop_order_payment_path(order)
      post shop_pay_order_path(order), params: { card_number: APPROVED_CARD }

      post shop_pay_order_path(order), params: { card_number: APPROVED_CARD }

      assert_response :not_found
      assert_equal 1, order.payments.count
    end

    test "a declined card is reported and the retry pays" do
      order = placed_order
      get shop_order_payment_path(order)

      post shop_pay_order_path(order), params: { card_number: UNFUNDED_CARD }

      assert_equal "payment_failed", order.reload.status
      follow_redirect!
      assert_select "[data-decline]", text: /insufficient funds/

      get shop_order_payment_path(order)
      assert_select "[data-decline]", text: /insufficient funds/

      post shop_pay_order_path(order), params: { card_number: APPROVED_CARD }

      assert_equal "paid", order.reload.status
      assert_equal 2, order.payments.count
    end

    test "a card number nobody recognises is declined" do
      order = placed_order
      get shop_order_payment_path(order)

      post shop_pay_order_path(order), params: { card_number: "1234" }

      follow_redirect!
      assert_select "[data-decline]", text: /not valid/
    end

    test "a retry answers 422 and names the line when another buyer took the last one first" do
      listing = create_listing(title: "Harbour at Dusk", quantity: 1)
      order = placed_order(listing)
      post shop_pay_order_path(order), params: { card_number: UNFUNDED_CARD }

      other_buyer = create_verified_customer(email: unique_email("rival"))
      paid_order_for(other_buyer, listing)

      post shop_pay_order_path(order), params: { card_number: APPROVED_CARD }

      assert_response :unprocessable_content
      assert_select "[data-blocked-line][data-reason=sold_out]", text: /Harbour at Dusk/
      assert_equal "payment_failed", order.reload.status
      assert_equal 1, order.payments.count
    end

    test "a blocked customer cannot pay" do
      order = placed_order
      get shop_order_payment_path(order)
      visiting_customer.block!(reason: "Chargeback fraud.", by: create_admin)

      post shop_pay_order_path(order), params: { card_number: APPROVED_CARD }

      assert_redirected_to shop_order_path(order)
      assert_equal "Your account is on hold, so you cannot add to a cart or check out. Chargeback fraud.",
        flash[:alert]
      assert_equal "awaiting_payment", order.reload.status
    end

    test "a lift restores paying" do
      order = placed_order
      get shop_order_payment_path(order)
      visiting_customer.block!(reason: "Chargeback fraud.", by: create_admin)
      visiting_customer.lift_block!

      post shop_pay_order_path(order), params: { card_number: APPROVED_CARD }

      assert_equal "paid", order.reload.status
    end

    private

    # A guest checkout followed by the magic link, which is the only way a card
    # form is reached.
    def placed_order(listing = create_listing)
      post shop_add_to_cart_path(slug: listing.slug)
      post shop_place_order_path, params: { email: "guest@example.com" }.merge(shipping_params)
      order = order_of_visiting_customer
      follow_magic_link

      order
    end
  end
end
