require "test_helper"

module Shop
  class CartsControllerTest < ActionDispatch::IntegrationTest
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

    test "it shows the lines and the subtotal" do
      artist = create_seller(shop_name: "Blue Kiln Studio")
      post shop_add_to_cart_path(slug: create_listing(artist, title: "Harbour at Dusk").slug)
      post shop_add_to_cart_path(slug: create_listing(artist, title: "Winter Field", price_cents: 12_000).slug)

      get shop_cart_path

      assert_response :success
      assert_select "a", text: "Harbour at Dusk"
      assert_select "a", text: "Winter Field"
      assert_select "[data-cart-subtotal]", text: "$570.00"
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

    test "an empty cart says so" do
      get shop_cart_path

      assert_select "p", text: "Your cart is empty."
    end

    test "the header counts what the cart holds" do
      post shop_add_to_cart_path(slug: create_listing(quantity: 3).slug), params: { quantity: 2 }

      get root_path

      assert_select "nav a", text: "Cart (2)"
    end

    test "a cart filled before signing in survives the merge" do
      listing = create_listing(title: "Harbour at Dusk")
      create_verified_customer(email: "buyer@example.com")
      post shop_add_to_cart_path(slug: listing.slug)
      guest = visiting_customer

      sign_in_as_customer(email: "buyer@example.com")

      get shop_cart_path
      assert_select "a", text: "Harbour at Dusk"
      refute_equal guest.id, visiting_customer.id
    end
  end
end
