require "test_helper"

class Admin::LedgerControllerTest < ActionDispatch::IntegrationTest
  test "a signed-out visitor is sent to the sign-in page" do
    get admin_ledger_path

    assert_redirected_to admin_login_path
  end

  test "it says so where the ledger holds nothing" do
    sign_in_as_admin

    get admin_ledger_path

    assert_select "[data-empty=?]", "entries"
  end

  test "it lists every entry, newest first" do
    sign_in_as_admin
    seller = create_seller
    fulfillment = held_fulfillment(seller)
    entry = LedgerEntry.where(fulfillment_id: fulfillment.id).sole

    get admin_ledger_path

    assert_select "[data-entry=?] [data-cell=?]", entry.id, "entry_type", text: "Held"
    assert_select "[data-entry=?] [data-cell=?]", entry.id, "amount", text: "$405.00"
  end

  test "the seller filter narrows to one seller's entries" do
    sign_in_as_admin
    seller = create_seller
    other = create_seller
    entry = LedgerEntry.where(fulfillment_id: held_fulfillment(seller).id).sole
    other_entry = LedgerEntry.where(fulfillment_id: held_fulfillment(other).id).sole

    get admin_ledger_path(seller: seller.id)

    assert_select "[data-entry=?]", entry.id.to_s
    assert_select "[data-entry=?]", other_entry.id.to_s, false
  end

  test "the type filter narrows to one entry type" do
    sign_in_as_admin
    seller = create_seller
    fulfillment = held_fulfillment(seller)
    fulfillment.ship!(carrier: "Royal Mail", tracking_number: "RM1", at: moment("2026-08-21 09:00:00"))
    fulfillment.deliver!(at: moment("2026-08-22 09:00:00"))
    held_entry = LedgerEntry.where(fulfillment_id: fulfillment.id, entry_type: "held").sole
    released_entry = LedgerEntry.where(fulfillment_id: fulfillment.id, entry_type: "released").sole

    get admin_ledger_path(type: "held")

    assert_select "[data-entry=?]", held_entry.id.to_s
    assert_select "[data-entry=?]", released_entry.id.to_s, false
  end

  test "the refunded filter value is accepted" do
    sign_in_as_admin
    seller = create_seller
    fulfillment = held_fulfillment(seller)
    fulfillment.decline!(reason: "Out of stock", by: seller, at: moment("2026-08-21 09:00:00"))
    refund_entry = LedgerEntry.where(fulfillment_id: fulfillment.id, entry_type: "refunded").sole

    get admin_ledger_path(type: "refunded")

    assert_response :success
    assert_select "[data-entry=?]", refund_entry.id.to_s
  end

  test "an id filter naming nobody narrows to nothing" do
    sign_in_as_admin
    held_fulfillment(create_seller)

    get admin_ledger_path(seller: unused_id(:sel))

    assert_select "[data-empty=?]", "entries"
  end

  test "an unknown seller filter value is a bad request" do
    sign_in_as_admin

    get admin_ledger_path(seller: "wat")

    assert_response :bad_request
  end

  test "an unknown type filter value is a bad request" do
    sign_in_as_admin

    get admin_ledger_path(type: "wat")

    assert_response :bad_request
  end

  test "the totals fold the filtered set, not the whole ledger" do
    sign_in_as_admin
    seller = create_seller
    held_fulfillment(seller)
    held_fulfillment(create_seller)

    get admin_ledger_path(seller: seller.id)

    assert_select "[data-stat=held] dd", text: "$405.00"
  end

  test "the totals fold to zero where the filter matches nothing" do
    sign_in_as_admin
    held_fulfillment(create_seller)

    get admin_ledger_path(seller: unused_id(:sel))

    assert_select "[data-stat=held] dd", text: "$0.00"
  end

  test "the folded totals cost the same statements however many sellers hold entries" do
    sign_in_as_admin
    held_fulfillment(create_seller)
    one = count_queries { get admin_ledger_path }

    4.times { held_fulfillment(create_seller) }
    five = count_queries { get admin_ledger_path }

    assert_equal one, five
  end

  private

  def held_fulfillment(seller)
    paid_order_for(create_verified_customer, create_listing(seller, price_cents: 45_000)).fulfillments.sole
  end
end
