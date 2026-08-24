require "test_helper"

module Shop
  class CartItemsControllerTest < ActionDispatch::IntegrationTest
    test "it adds a listing to the cart and records the event" do
      listing = create_listing

      post shop_add_to_cart_path(slug: listing.slug)

      assert_redirected_to shop_cart_path
      assert_equal 1, visiting_customer.current_cart.items.sole.quantity
      assert_equal "cart_add", listing.events.sole.event_type
    end

    test "it adds the quantity the visitor asked for" do
      listing = create_listing(quantity: 4)

      post shop_add_to_cart_path(slug: listing.slug), params: { quantity: 3 }

      assert_equal 3, visiting_customer.current_cart.items.sole.quantity
    end

    test "it never holds more of a listing than the seller has left" do
      listing = create_listing(quantity: 2)

      post shop_add_to_cart_path(slug: listing.slug), params: { quantity: 9 }

      assert_equal 2, visiting_customer.current_cart.items.sole.quantity
    end

    test "it removes a line" do
      listing = create_listing
      post shop_add_to_cart_path(slug: listing.slug)

      delete shop_remove_from_cart_path(slug: listing.slug)

      assert_redirected_to shop_cart_path
      assert_empty visiting_customer.current_cart.items
    end

    test "it refuses a listing that is not for sale" do
      listing = create_listing(status: "sold", quantity: 0)

      post shop_add_to_cart_path(slug: listing.slug)

      assert_redirected_to shop_listing_path(slug: listing.slug)
      assert_equal "That listing is no longer for sale.", flash[:alert]
      assert_empty visiting_customer.current_cart.items
    end

    test "a listing that was never public cannot reach a cart" do
      listing = create_listing(status: "draft")

      post shop_add_to_cart_path(slug: listing.slug)

      assert_response :not_found
    end

    test "a listing an admin removed cannot reach a cart" do
      listing = create_listing(status: "for_sale")
      listing.remove!(kind: :temporary, reason: "Reported.", by: create_admin)

      post shop_add_to_cart_path(slug: listing.slug)

      assert_response :not_found
    end

    test "a blocked customer cannot add to a cart" do
      listing = create_listing
      sign_in_as_customer
      visiting_customer.block!(reason: "Chargeback fraud.", by: create_admin)

      post shop_add_to_cart_path(slug: listing.slug)

      assert_redirected_to shop_listing_path(slug: listing.slug)
      assert_equal "Your account is on hold, so you cannot add to a cart or check out. Chargeback fraud.",
        flash[:alert]
      assert_empty visiting_customer.current_cart.items
    end

    test "a lift restores cart add" do
      listing = create_listing
      sign_in_as_customer
      visiting_customer.block!(reason: "Chargeback fraud.", by: create_admin)
      visiting_customer.lift_block!

      post shop_add_to_cart_path(slug: listing.slug)

      assert_redirected_to shop_cart_path
      assert_equal 1, visiting_customer.current_cart.items.sole.quantity
    end
  end
end
