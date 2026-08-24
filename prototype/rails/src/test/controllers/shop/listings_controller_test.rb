require "test_helper"

module Shop
  class ListingsControllerTest < ActionDispatch::IntegrationTest
    test "it shows the listing in full" do
      listing = create_listing(
        create_seller(shop_name: "Blue Kiln Studio"),
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

      event = listing.events.sole
      assert_equal "view", event.event_type
      assert_equal visiting_customer.id, event.customer_id
    end

    test "a sold listing says so and offers no cart button" do
      listing = create_listing(status: "sold", quantity: 0)

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
      listing = create_listing(status: "draft")

      get shop_listing_path(slug: listing.slug)

      assert_response :not_found
    end

    test "an unknown slug is not on the storefront" do
      get shop_listing_path(slug: "nothing-here")

      assert_response :not_found
    end

    test "it offers to ask the seller a question" do
      listing = create_listing

      get shop_listing_path(slug: listing.slug)

      assert_select "h2", text: "Ask the seller a question"
      assert_select "form[action=?][method=post] textarea[name=?]",
        shop_listing_questions_path(slug: listing.slug), "message[body]"
    end

    test "it reads the published questions in the order they went up" do
      listing = create_listing
      first = ListingFaq.publish(listing, question: "Is the frame included?", answer: "It is.")
      second = ListingFaq.publish(listing, question: "Does it ship abroad?", answer: "It does.")

      get shop_listing_path(slug: listing.slug)

      assert_select "h2", text: "Questions and answers"
      assert_select "[data-faq]", 2
      assert_select "[data-faq]:first-of-type", text: /Is the frame included\?/
      assert_select "[data-faq=?] dt", first.id.to_s, text: "Is the frame included?"
      assert_select "[data-faq=?] dd", second.id.to_s, text: "It does."
    end

    test "a listing with nothing published carries no FAQ heading" do
      get shop_listing_path(slug: create_listing.slug)

      assert_select "h2", text: "Questions and answers", count: 0
    end

    test "it offers to favorite a listing the visitor has not saved" do
      listing = create_listing

      get shop_listing_path(slug: listing.slug)

      assert_select "button", text: "Favorite"
    end
  end
end
