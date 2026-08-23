require "test_helper"

module Shop
  class CheckoutsControllerTest < ActionDispatch::IntegrationTest
    test "an empty cart goes back to the cart page" do
      get shop_checkout_path

      assert_redirected_to shop_cart_path
    end

    test "a guest gets no card fields before verifying" do
      fill_cart

      get shop_checkout_path

      assert_response :success
      assert_select "input[name=card_number]", count: 0
      assert_select "input[name=email]:not([readonly])"
      assert_select "[data-checkout-total]", text: "$450.00"
    end

    test "it prefills and locks the address of a signed-in customer" do
      sign_in_as_customer(email: "buyer@example.com")
      fill_cart

      get shop_checkout_path

      assert_select "input[name=email][readonly][value=?]", "buyer@example.com"
      assert_select "input[name=card_number]"
    end

    test "a guest places an order that waits for verification" do
      fill_cart

      post shop_place_order_path, params: { email: "guest@example.com" }.merge(shipping_params)

      order = order_of_visiting_customer
      assert_redirected_to shop_order_path(order)
      assert_equal "pending_verification", order.status
      assert_equal "guest@example.com", order.email
      assert_equal "London", order.shipping_city
      assert_empty order.payments
    end

    test "a guest is handed a link that lands on the pay page" do
      fill_cart

      assert_enqueued_emails 1 do
        post shop_place_order_path, params: { email: "guest@example.com" }.merge(shipping_params)
      end

      order = order_of_visiting_customer
      assert_equal shop_order_payment_path(order), MagicLink.sole.redirect_to
      assert_includes flash[:debug_magic_link], "/auth/magic/"
      assert_enqueued_email_with MagicLinkMailer, :sign_in,
        params: { link: MagicLink.sole, url: flash[:debug_magic_link] }
    end

    test "the order page explains that a link was sent" do
      fill_cart
      post shop_place_order_path, params: { email: "guest@example.com" }.merge(shipping_params)

      follow_redirect!

      assert_select "[data-verification-notice]", text: /guest@example.com/
      assert_select "[data-debug-alert]"
      assert_select "input[name=card_number]", count: 0
    end

    test "verifying carries the guest order to their account and pays it" do
      listing = fill_cart
      post shop_place_order_path, params: { email: "guest@example.com" }.merge(shipping_params)
      order = order_of_visiting_customer

      follow_magic_link

      assert_redirected_to shop_order_payment_path(order)
      follow_redirect!
      assert_equal "awaiting_payment", order.reload.status
      assert_select "input[name=card_number]"

      post shop_pay_order_path(order), params: { card_number: APPROVED_CARD }

      assert_redirected_to shop_order_path(order)
      assert_equal "paid", order.reload.status
      assert_equal order.customer_id, visiting_customer.id
      assert_equal "guest@example.com", visiting_customer.email
      assert_equal "sold", listing.reload.status
    end

    test "a signed-in customer pays as they place the order" do
      sign_in_as_customer(email: "buyer@example.com")
      fill_cart

      post shop_place_order_path,
        params: { email: "buyer@example.com", card_number: APPROVED_CARD }.merge(shipping_params)

      order = order_of_visiting_customer
      assert_redirected_to shop_order_path(order)
      assert_equal "paid", order.status
      assert_equal "4242", order.payments.sole.card_last_four
      assert_empty MagicLink.where(redirect_to: shop_order_payment_path(order))
    end

    test "a signed-in customer buys under the address on their account" do
      sign_in_as_customer(email: "buyer@example.com")
      fill_cart

      post shop_place_order_path,
        params: { email: "someone-else@example.com", card_number: APPROVED_CARD }.merge(shipping_params)

      assert_equal "buyer@example.com", order_of_visiting_customer.email
    end

    test "a declined card leaves the order unpaid with a reason and a retry form" do
      listing = fill_cart
      sign_in_as_customer(email: "buyer@example.com")

      post shop_place_order_path,
        params: { email: "buyer@example.com", card_number: DECLINED_CARD }.merge(shipping_params)

      order = order_of_visiting_customer
      assert_equal "payment_failed", order.status
      assert_equal "for_sale", listing.reload.status

      follow_redirect!
      assert_select "[data-decline]", text: /Your card was declined/
      assert_select "input[name=card_number]"
    end

    test "an incomplete address is refused before an order is opened" do
      fill_cart

      post shop_place_order_path, params: { email: "guest@example.com", shipping_name: "Ada Lovelace" }

      assert_response :unprocessable_content
      assert_select "[data-flash=alert]", text: /Enter an email address and a full shipping address/
      assert_select "input[name=shipping_name][value=?]", "Ada Lovelace"
      assert_empty Order.all
    end

    test "an empty cart cannot be checked out" do
      post shop_place_order_path, params: { email: "guest@example.com" }.merge(shipping_params)

      assert_redirected_to shop_cart_path
      assert_empty Order.all
    end

    private

    def fill_cart
      create_listing.tap { |listing| post shop_add_to_cart_path(slug: listing.slug) }
    end
  end
end
