require "test_helper"

class Admin::ListingsControllerTest < ActionDispatch::IntegrationTest
  test "a signed-out visitor is sent to the sign-in page" do
    get admin_listings_path

    assert_redirected_to admin_login_path
  end

  test "the list reaches across sellers" do
    sign_in_as_admin
    mine = create_listing(create_seller, title: "Harbour at Dusk")
    theirs = create_listing(other_seller, title: "Quiet Orchard")

    get admin_listings_path

    assert_response :success
    assert_select "[data-listing=?] a[href=?]", mine.id, admin_listing_path(mine), text: "Harbour at Dusk"
    assert_select "[data-listing=?] a[href=?]", theirs.id, admin_listing_path(theirs), text: "Quiet Orchard"
  end

  test "the status filter narrows to one status at a time" do
    sign_in_as_admin
    seller = create_seller
    listings = Listing.statuses.keys.index_with { |status| create_listing(seller, status: status) }

    Listing.statuses.keys.each do |status|
      get admin_listings_path(status: status)

      listings.each { |own, listing| assert_select "[data-listing=?]", listing.id, own == status }
    end
  end

  test "an empty status filter keeps every status" do
    sign_in_as_admin
    draft = create_listing(create_seller, status: :draft)
    for_sale = create_listing(create_seller, status: :for_sale)

    get admin_listings_path(status: "")

    assert_select "[data-listing=?]", draft.id
    assert_select "[data-listing=?]", for_sale.id
  end

  test "a status the page does not offer is a bad request" do
    sign_in_as_admin

    get admin_listings_path(status: "wat")

    assert_response :bad_request
  end

  test "the seller filter narrows to one seller's catalogue" do
    sign_in_as_admin
    seller = create_seller
    mine = create_listing(seller)
    theirs = create_listing(other_seller)

    get admin_listings_path(seller: seller.id)

    assert_select "[data-listing=?]", mine.id
    assert_select "[data-listing=?]", theirs.id, false
  end

  test "an empty seller filter keeps every seller" do
    sign_in_as_admin
    mine = create_listing(create_seller)
    theirs = create_listing(other_seller)

    get admin_listings_path(seller: "")

    assert_select "[data-listing=?]", mine.id
    assert_select "[data-listing=?]", theirs.id
  end

  test "a seller filter carrying another table's id is a bad request" do
    sign_in_as_admin

    get admin_listings_path(seller: unused_id(:cus))

    assert_response :bad_request
  end

  test "a seller filter naming nobody narrows to nothing" do
    sign_in_as_admin
    create_listing

    get admin_listings_path(seller: unused_id(:sel))

    assert_select "[data-empty=?]", "listings"
  end

  test "removed=any keeps every listing" do
    sign_in_as_admin
    listing = create_listing

    get admin_listings_path(removed: "any")

    assert_select "[data-listing=?]", listing.id
  end

  test "removed=visible keeps the listings no removal stands over" do
    sign_in_as_admin
    listing = create_listing

    get admin_listings_path(removed: "visible")

    assert_select "[data-listing=?]", listing.id
  end

  test "removed=removed lists nothing while no removal stands" do
    sign_in_as_admin
    create_listing

    get admin_listings_path(removed: "removed")

    assert_select "[data-empty=?]", "listings"
  end

  test "an empty removal filter keeps every listing" do
    sign_in_as_admin
    listing = create_listing

    get admin_listings_path(removed: "")

    assert_select "[data-listing=?]", listing.id
  end

  test "a removal filter the page does not offer is a bad request" do
    sign_in_as_admin

    get admin_listings_path(removed: "wat")

    assert_response :bad_request
  end

  test "the list says so where nothing matches" do
    sign_in_as_admin

    get admin_listings_path

    assert_select "[data-empty=?]", "listings"
  end

  test "the list costs the same however many listings it holds" do
    sign_in_as_admin
    seller = create_seller
    create_listing(seller)
    one = count_queries { get admin_listings_path }

    4.times { create_listing(seller) }
    five = count_queries { get admin_listings_path }

    assert_equal one, five
  end

  test "the page reads one listing whoever owns it" do
    sign_in_as_admin
    listing = create_listing(other_seller, title: "Quiet Orchard")

    get admin_listing_path(listing)

    assert_response :success
    assert_select "h1", text: "Quiet Orchard"
    assert_select "[data-field=?] a[href=?]", "seller", admin_seller_path(listing.seller)
    assert_select "[data-field=?]", "status", text: "For sale"
    assert_select "[data-field=?]", "storefront", text: "On the storefront"
  end

  test "a draft is off the storefront" do
    sign_in_as_admin
    listing = create_listing(status: :draft)

    get admin_listing_path(listing)

    assert_select "[data-field=?]", "storefront", text: "Off the storefront"
  end

  test "the page says so where no removal stands over the listing" do
    sign_in_as_admin

    get admin_listing_path(create_listing)

    assert_select "[data-empty=?]", "listing_removals"
  end

  test "a listing path carrying another table's id is not found" do
    sign_in_as_admin

    get "/admin/listings/#{unused_id(:sel)}"

    assert_response :not_found
  end

  test "a listing id nothing was written for is not found" do
    sign_in_as_admin

    get admin_listing_path(id: unused_id(:lst))

    assert_response :not_found
  end
end
