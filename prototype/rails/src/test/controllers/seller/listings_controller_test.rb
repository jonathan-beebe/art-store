require "test_helper"

class Seller::ListingsControllerTest < ActionDispatch::IntegrationTest
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
    create_listing_event(listing, "view", moment("2026-08-20 09:00:00"))
    create_listing_event(listing, "view", moment("2026-08-21 09:00:00"))
    create_listing_event(listing, "cart_add", moment("2026-08-21 10:00:00"))

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

  test "the index orders listings by creation time, not by mint order" do
    seller = signed_in_seller
    minted_first = create_listing(seller, title: "Minted First", created_at: 1.day.ago)
    minted_second = create_listing(seller, title: "Minted Second", created_at: 5.days.ago)

    get seller_listings_path

    assert_select "tbody tr:first-child[data-listing=?]", minted_first.id
    assert_select "tbody tr:last-child[data-listing=?]", minted_second.id
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
    listing = create_listing(seller, status: "draft")

    get seller_listings_path

    assert_select "[data-status-button=for_sale]"
    assert_select "[data-status-button=archived]"
    assert_select "[data-status-button=sold]", false
  end

  test "the activity page totals the events of the seller's own listing" do
    seller = signed_in_seller
    listing = create_listing(seller)
    create_listing_event(listing, "view", 2.days.ago)
    create_listing_event(listing, "view", 1.day.ago)
    create_listing_event(listing, "favorite", 1.day.ago)
    create_listing_event(listing, "unfavorite", 1.day.ago)

    get seller_listing_path(listing)

    assert_response :success
    assert_select "[data-stat=views]", text: "2"
    assert_select "[data-stat=favorites]", text: "1"
    assert_select "[data-stat=cart_adds]", text: "0"
  end

  test "the activity page breaks the last fortnight down by day" do
    seller = signed_in_seller
    listing = create_listing(seller)
    create_listing_event(listing, "view", 1.day.ago)
    create_listing_event(listing, "view", 40.days.ago)

    get seller_listing_path(listing)

    assert_select "tbody tr[data-day]", 14
    assert_select "[data-day=?]", Date.current.yesterday.iso8601 do
      assert_select "[data-activity=views]", text: "1"
    end
    assert_select "[data-day=?]", Date.current.iso8601 do
      assert_select "[data-activity=views]", text: "0"
    end
  end

  test "the activity page lists the orders that bought the listing" do
    seller = signed_in_seller
    listing = create_listing(seller, quantity: 2)
    order = create_paid_order(listing)

    get seller_listing_path(listing)

    assert_select "[data-sale]", 1
    assert_select "[data-sale] th", text: "##{order.id}"
    assert_select "[data-cell=order_status]", text: "Paid"
  end

  test "a listing with no sales says so" do
    seller = signed_in_seller

    get seller_listing_path(create_listing(seller))

    assert_select "[data-sale]", false
    assert_select "p", text: "No sales yet."
  end

  test "another seller's activity page is not found" do
    signed_in_seller

    get seller_listing_path(create_listing(other_seller))

    assert_response :not_found
  end

  test "a signed-out visitor reaches no activity page" do
    get seller_listing_path(create_listing(other_seller))

    assert_redirected_to seller_login_path
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
    assert_equal "draft", listing.status
    assert_equal "harbour-at-dusk", listing.slug
    follow_redirect!
    assert_select "[data-flash=notice]", text: /is saved as a draft/
  end

  test "creating a listing attaches an uploaded image" do
    seller = signed_in_seller

    post seller_listings_path, params: { listing: submitted_fields(image: uploaded_image) }

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

    post seller_listings_path, params: { listing: submitted_fields(image: uploaded_pdf) }

    assert_response :unprocessable_content
    assert_select "[data-field-error=listing_image]", text: "Upload an image file."
    assert_empty seller.listings
  end

  test "an SVG upload is refused whatever its declared content type" do
    seller = signed_in_seller

    post seller_listings_path, params: { listing: submitted_fields(image: uploaded_svg) }

    assert_response :unprocessable_content
    assert_select "[data-field-error=listing_image]", text: "Upload an image file."
    assert_empty seller.listings
  end

  test "an upload over the size cap is refused, and the form is re-rendered with what the seller typed" do
    seller = signed_in_seller

    post seller_listings_path, params: {
      listing: submitted_fields(title: "Harbour at Dusk", image: oversized_upload)
    }

    assert_response :unprocessable_content
    assert_select "[data-field-error=listing_image]", text: "Upload an image under 5 MB."
    assert_select "input[name=?][value=?]", "listing[title]", "Harbour at Dusk"
    assert_empty seller.listings
  end

  test "a real image declared as something else is accepted on its bytes" do
    seller = signed_in_seller

    post seller_listings_path, params: {
      listing: submitted_fields(image: upload("text/plain", "\x89PNG\r\n\x1a\n", "harbour.txt"))
    }

    assert_predicate seller.listings.sole.image, :attached?
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

  test "a listing path carrying another table's id is not found" do
    signed_in_seller

    get "/seller/listings/#{unused_id(:ord)}"

    assert_response :not_found
  end

  test "a listing path carrying a ulid with no prefix is not found" do
    seller = signed_in_seller
    listing = create_listing(seller)

    get "/seller/listings/#{listing.id.delete_prefix('lst_')}"

    assert_response :not_found
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

  def uploaded_image
    upload("image/png", "\x89PNG\r\n\x1a\n", "harbour.png")
  end

  # `ImageFormat` reads the type out of the bytes, so a refused upload carries
  # a real header rather than a claim in the request.
  def uploaded_pdf
    upload("application/pdf", "%PDF-1.4\n", "harbour.pdf")
  end

  def uploaded_svg
    upload("image/svg+xml", '<svg xmlns="http://www.w3.org/2000/svg"></svg>', "harbour.svg")
  end

  def oversized_upload
    png = "\x89PNG\r\n\x1a\n"
    upload("image/png", png + ("\x00" * (Listing::MAX_IMAGE_UPLOAD_BYTES - png.bytesize + 1)), "harbour.png")
  end

  def upload(content_type, bytes, filename)
    Rack::Test::UploadedFile.new(StringIO.new(bytes), content_type, true, original_filename: filename)
  end
end
