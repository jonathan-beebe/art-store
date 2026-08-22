require "shop_test_case"

module Shop
  class ListingsControllerTest < ShopIntegrationTest
    test "it shows the listing in full" do
      listing = create_listing(
        seller: create_artist(shop_name: "Blue Kiln Studio"),
        title: "Harbour at Dusk",
        description: "An oil study of the harbour after sundown.",
        medium: "Oil on canvas",
        dimensions: "40 x 60 cm"
      )

      get shop_listing_path(slug: listing.slug)

      assert_response :success
      assert_select "h1", text: "Harbour at Dusk"
      assert_select "p", text: "Blue Kiln Studio"
      assert_select "p", text: "$450.00"
      assert_select "dd", text: "Oil on canvas"
      assert_select "dd", text: "40 x 60 cm"
      assert_select "p", text: "An oil study of the harbour after sundown."
      assert_select "img[alt=?]", "Harbour at Dusk"
    end

    test "it records a view event for the visitor" do
      listing = create_listing

      get shop_listing_path(slug: listing.slug)

      event = listing.listing_events.sole
      assert_equal Domain::Listings::ListingEventType::VIEW, event.event_type
      assert_equal visiting_customer.id, event.customer_id
    end

    test "a sold listing says so and offers no cart button" do
      listing = create_listing(status: Domain::Listings::ListingStatus::SOLD, quantity: 0)

      get shop_listing_path(slug: listing.slug)

      assert_response :success
      assert_select "[data-availability]", text: "Sold"
      assert_select "input[value=?]", "Add to cart", count: 0
    end

    test "a for-sale listing offers the cart button" do
      listing = create_listing(quantity: 3)

      get shop_listing_path(slug: listing.slug)

      assert_select "[data-availability]", text: "3"
      assert_select "input[value=?]", "Add to cart"
      assert_select "input[name=quantity][max=?]", "3"
    end

    test "a draft listing is not on the storefront" do
      listing = create_listing(status: Domain::Listings::ListingStatus::DRAFT)

      get shop_listing_path(slug: listing.slug)

      assert_response :not_found
    end

    test "an unknown slug is not on the storefront" do
      get shop_listing_path(slug: "nothing-here")

      assert_response :not_found
    end

    test "it offers to favorite a listing the visitor has not saved" do
      listing = create_listing

      get shop_listing_path(slug: listing.slug)

      assert_select "button", text: "Favorite"
    end
  end
end
