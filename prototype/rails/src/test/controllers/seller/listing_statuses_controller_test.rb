require "test_helper"

class Seller::ListingStatusesControllerTest < ActionDispatch::IntegrationTest
  test "a signed-out visitor changes nothing" do
    listing = create_listing(other_seller, status: "draft")

    post seller_listing_status_path(listing), params: { status: "for_sale" }

    assert_redirected_to seller_login_path
    assert_equal "draft", listing.reload.status
  end

  test "a draft the lifecycle allows to go on sale goes on sale" do
    seller = signed_in_seller
    listing = create_listing(seller, status: "draft")

    post seller_listing_status_path(listing), params: { status: "for_sale" }

    assert_redirected_to seller_listings_path
    assert_equal "for_sale", listing.reload.status
    follow_redirect!
    assert_select "[data-flash=notice]", text: /is now for sale/
  end

  test "a move the lifecycle refuses answers 422 and says why" do
    seller = signed_in_seller
    listing = create_listing(seller, status: "draft")

    post seller_listing_status_path(listing), params: { status: "sold" }

    assert_response :unprocessable_content
    assert_select "[data-refusal]", text: "A listing cannot move from draft to sold."
    assert_equal "draft", listing.reload.status
  end

  test "a status nothing in the lifecycle names answers 422" do
    seller = signed_in_seller
    listing = create_listing(seller, status: "draft")

    post seller_listing_status_path(listing), params: { status: "on_loan" }

    assert_response :unprocessable_content
    assert_equal "draft", listing.reload.status
  end

  test "a listing an admin removed cannot be put back on sale" do
    seller = signed_in_seller
    listing = create_listing(seller, status: "draft")
    listing.remove!(kind: :temporary, reason: "Reported as counterfeit.", by: create_admin)

    post seller_listing_status_path(listing), params: { status: "for_sale" }

    assert_response :unprocessable_content
    assert_select "[data-refusal]",
      text: "This listing was removed by an admin and cannot be put back on sale."
    assert_equal "draft", listing.reload.status
  end

  test "the seller reads why their listing was removed and gets no for-sale button" do
    seller = signed_in_seller
    listing = create_listing(seller, status: "draft")
    listing.remove!(kind: :temporary, reason: "Reported as counterfeit.", by: create_admin)

    get seller_listings_path

    assert_select "[data-listing=?] form[data-status-button=?]", listing.id, "for_sale", false
    assert_select "[data-listing=?] form[data-status-button=?]", listing.id, "archived"

    get seller_listing_path(listing)

    assert_select "[data-field=removal]", text: /Reported as counterfeit\./
  end

  test "changing another seller's listing is not found" do
    signed_in_seller
    rival = create_listing(other_seller, status: "draft")

    post seller_listing_status_path(rival), params: { status: "for_sale" }

    assert_response :not_found
    assert_equal "draft", rival.reload.status
  end
end
