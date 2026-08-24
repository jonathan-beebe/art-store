require "test_helper"

class Seller::DeclinesControllerTest < ActionDispatch::IntegrationTest
  test "a signed-out visitor declines nothing" do
    fulfillment = create_fulfillment(other_seller)

    post seller_order_decline_path(fulfillment), params: decline

    assert_redirected_to seller_login_path
    assert_predicate fulfillment.reload, :awaiting_shipment?
  end

  test "declining sends the money back and puts the stock up again" do
    seller = signed_in_seller
    listing = create_listing(seller, quantity: 1)
    fulfillment = create_fulfillment(seller, listing: listing)

    post seller_order_decline_path(fulfillment), params: decline

    assert_redirected_to seller_order_path(fulfillment)
    assert_predicate fulfillment.reload, :declined?
    assert_equal "The kiln cracked it.", fulfillment.refunds.sole.reason
    assert_equal "for_sale", listing.reload.status
    follow_redirect!
    assert_select "[data-flash=notice]", text: "Declined. The money is on its way back."
    assert_select "[data-cell=refund_reason]", text: "The kiln cracked it."
  end

  test "declining after shipping is refused" do
    seller = signed_in_seller
    fulfillment = create_fulfillment(seller)
    fulfillment.ship!(carrier: "Royal Mail", tracking_number: "RM123")

    post seller_order_decline_path(fulfillment), params: decline

    assert_response :unprocessable_content
    assert_select "[data-refusal]", text: "A fulfillment cannot move from shipped to declined."
    assert_predicate fulfillment.reload, :shipped?
  end

  test "a decline with no reason is refused" do
    seller = signed_in_seller
    fulfillment = create_fulfillment(seller)

    post seller_order_decline_path(fulfillment), params: decline(reason: "  ")

    assert_response :unprocessable_content
    assert_select "[data-refusal]", text: Refund::MISSING_REASON
    assert_predicate fulfillment.reload, :awaiting_shipment?
  end

  test "declining another seller's fulfillment is not found" do
    signed_in_seller
    rival = create_fulfillment(other_seller)

    post seller_order_decline_path(rival), params: decline

    assert_response :not_found
    assert_predicate rival.reload, :awaiting_shipment?
  end

  test "the order page offers the form only while the seller can still pull out" do
    seller = signed_in_seller
    fulfillment = create_fulfillment(seller)

    get seller_order_path(fulfillment)
    assert_select "input[type=submit][value=?]", "Decline and refund"

    fulfillment.ship!(carrier: "Royal Mail", tracking_number: "RM123")

    get seller_order_path(fulfillment)
    assert_select "input[type=submit][value=?]", "Decline and refund", count: 0
  end

  test "the earnings page shows the refund as a movement" do
    seller = signed_in_seller
    fulfillment = create_fulfillment(seller)

    post seller_order_decline_path(fulfillment), params: decline

    get seller_earnings_path

    assert_select "[data-cell=entry_type]", text: "Refunded"
    assert_select "[data-movement] [data-cell=amount]", text: "-$405.00"
  end

  private

  def decline(**overrides)
    { reason: "The kiln cracked it." }.merge(overrides)
  end
end
