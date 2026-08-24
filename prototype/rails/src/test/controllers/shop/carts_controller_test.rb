require "test_helper"

module Shop
  class CartsControllerTest < ActionDispatch::IntegrationTest
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

    test "a line archived after it was added is marked with its reason" do
      listing = create_listing(title: "Harbour at Dusk")
      post shop_add_to_cart_path(slug: listing.slug)
      listing.update!(status: "archived")

      get shop_cart_path

      assert_select "li[data-reason=off_sale]", text: /Harbour at Dusk/
      assert_select "[data-unavailable-reason]", text: "no longer for sale"
    end

    test "a line removed by an admin after it was added is marked, excluded from the total, and checkout stays disabled" do
      listing = create_listing(title: "Harbour at Dusk", price_cents: 45_000)
      kept = create_listing(title: "Winter Field", price_cents: 12_000)
      post shop_add_to_cart_path(slug: listing.slug)
      post shop_add_to_cart_path(slug: kept.slug)

      listing.remove!(kind: :temporary, reason: "Reported as counterfeit.", by: create_admin)

      get shop_cart_path

      assert_select "li[data-reason=removed]", text: /Harbour at Dusk/
      assert_select "[data-unavailable-reason]", text: "no longer available"
      assert_select "[data-cart-subtotal]", text: "$120.00"
      assert_select "[data-cart-subtotal-note]"
      assert_select "a", text: "Checkout", count: 0
      assert_select "[data-checkout-disabled]", text: "Checkout"
    end

    test "checkout is disabled while a blocked line sits in the cart" do
      listing = create_listing
      post shop_add_to_cart_path(slug: listing.slug)
      listing.update!(quantity: 0)

      get shop_cart_path

      assert_select "a", text: "Checkout", count: 0
      assert_select "[data-checkout-disabled]", text: "Checkout"
    end

    test "checkout stays live while nothing in the cart is blocked" do
      post shop_add_to_cart_path(slug: create_listing.slug)

      get shop_cart_path

      assert_select "a", text: "Checkout"
      assert_select "[data-checkout-disabled]", count: 0
    end
  end
end
