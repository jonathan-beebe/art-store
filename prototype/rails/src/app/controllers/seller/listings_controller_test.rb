require "seller_portal_test_case"

class Seller::ListingsControllerTest < SellerPortalTestCase
  test "a signed-out visitor reaches no listing page" do
    listing = create_listing(other_seller)

    get seller_listings_path
    assert_redirected_to seller_login_path

    get new_seller_listing_path
    assert_redirected_to seller_login_path

    get edit_seller_listing_path(listing)
    assert_redirected_to seller_login_path

    post seller_listings_path, params: { listing: submitted_fields }
    assert_redirected_to seller_login_path
  end

  test "the index lists the seller's own listings with their activity" do
    seller = signed_in_seller
    listing = create_listing(seller, title: "Harbour at Dusk", price_cents: 45_000, quantity: 2)
    create_listing_event(listing, Domain::Listings::ListingEventType::VIEW, moment("2026-08-20 09:00:00"))
    create_listing_event(listing, Domain::Listings::ListingEventType::VIEW, moment("2026-08-21 09:00:00"))
    create_listing_event(listing, Domain::Listings::ListingEventType::CART_ADD, moment("2026-08-21 10:00:00"))

    get seller_listings_path

    assert_response :success
    assert_select "[data-listing=?]", listing.id.to_s do
      assert_select "[data-cell=status]", text: "For sale"
      assert_select "td", text: "$450.00"
      assert_select "[data-activity=views]", text: "2"
      assert_select "[data-activity=favorites]", text: "0"
      assert_select "[data-activity=cart_adds]", text: "1"
      assert_select "a[href=?]", edit_seller_listing_path(listing)
    end
  end

  test "another seller's listings stay off the index" do
    signed_in_seller
    rival = create_listing(other_seller, title: "Rival Work")

    get seller_listings_path

    assert_select "[data-listing=?]", rival.id.to_s, false
    assert_select "tbody tr", false
  end

  test "the index offers only the transitions the lifecycle allows" do
    seller = signed_in_seller
    listing = create_listing(seller, status: Domain::Listings::ListingStatus::DRAFT)

    get seller_listings_path

    assert_select "[data-status-button=for_sale]"
    assert_select "[data-status-button=archived]"
    assert_select "[data-status-button=sold]", false
  end

  test "the new listing form asks for every field" do
    signed_in_seller

    get new_seller_listing_path

    assert_response :success
    assert_select "form[action=?][method=post]", seller_listings_path
    assert_select "label[for=listing_title]"
    assert_select "label[for=listing_price]"
    assert_select "label[for=listing_image]"
    assert_select "input[name=?][type=file]", "listing[image]"
  end

  test "creating a listing stores it as a draft priced in cents" do
    seller = signed_in_seller

    post seller_listings_path, params: { listing: submitted_fields }

    assert_redirected_to seller_listings_path
    listing = seller.listings.sole
    assert_equal "Harbour at Dusk", listing.title
    assert_equal 24_900, listing.price_cents
    assert_equal Domain::Listings::ListingStatus::DRAFT, listing.status
    assert_equal "harbour-at-dusk", listing.slug
    follow_redirect!
    assert_select "[data-flash=notice]", text: /is saved as a draft/
  end

  test "creating a listing attaches an uploaded image" do
    seller = signed_in_seller

    post seller_listings_path, params: { listing: submitted_fields(image: uploaded_image("image/png")) }

    assert_predicate seller.listings.sole.image, :attached?
  end

  test "a price that is not an amount in dollars is refused" do
    seller = signed_in_seller

    post seller_listings_path, params: { listing: submitted_fields(price: "free") }

    assert_response :unprocessable_content
    assert_select "[data-field-error=listing_price]", text: /amount in dollars/
    assert_empty seller.listings
  end

  test "a listing with no title is refused" do
    seller = signed_in_seller

    post seller_listings_path, params: { listing: submitted_fields(title: " ") }

    assert_response :unprocessable_content
    assert_select "[data-field-error=listing_title]", text: "Enter a title."
    assert_empty seller.listings
  end

  test "an upload that is not an image is refused" do
    seller = signed_in_seller

    post seller_listings_path, params: { listing: submitted_fields(image: uploaded_image("application/pdf")) }

    assert_response :unprocessable_content
    assert_select "[data-field-error=listing_image]", text: "Upload an image file."
    assert_empty seller.listings
  end

  test "the edit form is filled with the listing as it stands" do
    seller = signed_in_seller
    listing = create_listing(seller, title: "Harbour at Dusk", price_cents: 45_000, quantity: 3)

    get edit_seller_listing_path(listing)

    assert_response :success
    assert_select "input[name=?][value=?]", "listing[title]", "Harbour at Dusk"
    assert_select "input[name=?][value=?]", "listing[price]", "450.00"
    assert_select "input[name=?][value=?]", "listing[quantity]", "3"
  end

  test "editing another seller's listing is not found" do
    signed_in_seller
    rival = create_listing(other_seller)

    get edit_seller_listing_path(rival)

    assert_response :not_found
  end

  test "updating a listing writes the edited fields" do
    seller = signed_in_seller
    listing = create_listing(seller)

    patch seller_listing_path(listing), params: { listing: submitted_fields(title: "Harbour at Dawn") }

    assert_redirected_to seller_listings_path
    assert_equal "Harbour at Dawn", listing.reload.title
    assert_equal 24_900, listing.price_cents
  end

  test "an edit that fails validation leaves the listing alone" do
    seller = signed_in_seller
    listing = create_listing(seller, title: "Harbour at Dusk")

    patch seller_listing_path(listing), params: { listing: submitted_fields(quantity: "1000") }

    assert_response :unprocessable_content
    assert_select "[data-field-error=listing_quantity]"
    assert_equal "Harbour at Dusk", listing.reload.title
  end

  test "updating another seller's listing is not found" do
    signed_in_seller
    rival = create_listing(other_seller, title: "Rival Work")

    patch seller_listing_path(rival), params: { listing: submitted_fields }

    assert_response :not_found
    assert_equal "Rival Work", rival.reload.title
  end

  private

  def submitted_fields(**overrides)
    {
      title: "Harbour at Dusk",
      description: "Oil on canvas.",
      medium: "Oil",
      dimensions: "40 x 60 cm",
      price: "249.00",
      quantity: "2"
    }.merge(overrides)
  end

  def uploaded_image(content_type)
    Rack::Test::UploadedFile.new(
      StringIO.new("\x89PNG\r\n\x1a\n"), content_type, true, original_filename: "harbour.png"
    )
  end
end
