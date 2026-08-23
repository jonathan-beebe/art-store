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
  end
end
