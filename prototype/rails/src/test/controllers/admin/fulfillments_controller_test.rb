require "test_helper"

class Admin::FulfillmentsControllerTest < ActionDispatch::IntegrationTest
  test "a signed-out visitor is sent to the sign-in page" do
    get admin_fulfillments_path

    assert_redirected_to admin_login_path
  end

  test "the list reaches across sellers" do
    sign_in_as_admin
    mine = create_fulfillment(create_seller)
    theirs = create_fulfillment(other_seller)

    get admin_fulfillments_path

    assert_response :success
    assert_select "[data-fulfillment=?] a[href=?]", mine.id, admin_fulfillment_path(mine)
    assert_select "[data-fulfillment=?] a[href=?]", theirs.id, admin_fulfillment_path(theirs)
  end

  test "the status filter narrows to one status at a time" do
    sign_in_as_admin
    fulfillment = create_fulfillment(create_seller)
    statuses = Fulfillment.statuses.keys

    statuses.each do |status|
      fulfillment.update!(status: status)

      get admin_fulfillments_path(status: status)
      assert_select "[data-fulfillment=?]", fulfillment.id

      get admin_fulfillments_path(status: statuses.find { |other| other != status })
      assert_select "[data-fulfillment=?]", fulfillment.id, false
    end
  end

  test "an empty status filter keeps every status" do
    sign_in_as_admin
    fulfillment = create_fulfillment(create_seller)

    get admin_fulfillments_path(status: "")

    assert_select "[data-fulfillment=?]", fulfillment.id
  end

  test "a status the page does not offer is a bad request" do
    sign_in_as_admin

    get admin_fulfillments_path(status: "wat")

    assert_response :bad_request
  end

  test "the seller filter narrows to one seller's sales" do
    sign_in_as_admin
    seller = create_seller
    mine = create_fulfillment(seller)
    theirs = create_fulfillment(other_seller)

    get admin_fulfillments_path(seller: seller.id)

    assert_select "[data-fulfillment=?]", mine.id
    assert_select "[data-fulfillment=?]", theirs.id, false
  end

  test "an empty seller filter keeps every seller" do
    sign_in_as_admin
    fulfillment = create_fulfillment(create_seller)

    get admin_fulfillments_path(seller: "")

    assert_select "[data-fulfillment=?]", fulfillment.id
  end

  test "a seller filter carrying another table's id is a bad request" do
    sign_in_as_admin

    get admin_fulfillments_path(seller: unused_id(:cus))

    assert_response :bad_request
  end

  test "the list says so where nothing matches" do
    sign_in_as_admin

    get admin_fulfillments_path

    assert_select "[data-empty=?]", "fulfillments"
  end

  test "the list costs the same however many fulfillments it holds" do
    sign_in_as_admin
    seller = create_seller
    create_fulfillment(seller)
    one = count_queries { get admin_fulfillments_path }

    4.times { create_fulfillment(seller) }
    five = count_queries { get admin_fulfillments_path }

    assert_equal one, five
  end

  test "the page reads one fulfillment with the lines its seller ships" do
    sign_in_as_admin
    seller = create_seller(shop_name: "Terra & Glaze")
    listing = create_listing(seller, title: "Harbour at Dusk")
    fulfillment = create_fulfillment(seller, listing: listing)

    get admin_fulfillment_path(fulfillment)

    assert_response :success
    assert_select "h1", text: "Fulfillment #{fulfillment.id}"
    assert_select "[data-field=?] a[href=?]", "order", admin_order_path(fulfillment.order)
    assert_select "[data-field=?] a[href=?]", "seller", admin_seller_path(seller)
    assert_select "[data-field=?]", "net", text: "$405.00"
    assert_select "[data-item] a", text: "Harbour at Dusk"
  end

  test "the page says so where nothing has been sent back" do
    sign_in_as_admin

    get admin_fulfillment_path(create_fulfillment(create_seller))

    assert_select "[data-empty=?]", "fulfillment_refunds"
  end

  test "a fulfillment path carrying another table's id is not found" do
    sign_in_as_admin

    get "/admin/fulfillments/#{unused_id(:ord)}"

    assert_response :not_found
  end

  test "a fulfillment id nothing was written for is not found" do
    sign_in_as_admin

    get admin_fulfillment_path(id: unused_id(:ful))

    assert_response :not_found
  end
end
