require "test_helper"

class OrderPlacementTest < ActiveSupport::TestCase
  test "a line still for sale within stock is placeable" do
    assert_nil OrderPlacement.reason_for(line)
  end

  test "an admin removal is unavailable" do
    assert_equal :removed, OrderPlacement.reason_for(line(removed: true))
  end

  test "a listing another buyer took is sold out" do
    assert_equal :sold_out, OrderPlacement.reason_for(line(status: "sold", available_quantity: 0))
  end

  test "an archived listing is off sale" do
    assert_equal :off_sale, OrderPlacement.reason_for(line(status: "archived"))
  end

  test "a listing back to draft is off sale" do
    assert_equal :off_sale, OrderPlacement.reason_for(line(status: "draft"))
  end

  test "a for-sale listing with nothing left is sold out" do
    assert_equal :sold_out, OrderPlacement.reason_for(line(available_quantity: 0))
  end

  test "a cart asking for more than is left is short of stock" do
    assert_equal :short_stock, OrderPlacement.reason_for(line(available_quantity: 1, quantity: 2))
  end

  test "nothing left to sell reads as sold out rather than short of stock" do
    assert_equal :sold_out, OrderPlacement.reason_for(line(available_quantity: 0, quantity: 2))
  end

  test "a removal outranks whatever the listing status says" do
    assert_equal :removed, OrderPlacement.reason_for(line(status: "sold", removed: true))
  end

  test "every line standing in the way is named, not just the first" do
    buyer = create_verified_customer
    low_tide = create_listing(title: "Low tide")
    harbour = create_listing(title: "Harbour at dusk")
    long_shore = create_listing(title: "Long shore")
    cart = cart_holding(buyer, low_tide, harbour, long_shore)

    low_tide.update!(status: "sold", quantity: 0)
    long_shore.update!(status: "archived")

    plan = OrderPlacement.plan(cart.items.includes(:listing))

    refute_predicate plan, :ok?
    assert_equal(
      [ [ low_tide.id, :sold_out ], [ long_shore.id, :off_sale ] ],
      plan.blocked_lines.map { |blocked| [ blocked.listing_id, blocked.reason ] }
    )
  end

  test "a cart of listings still for sale is placeable" do
    buyer = create_verified_customer
    cart = cart_holding(buyer, create_listing)

    plan = OrderPlacement.plan(cart.items.includes(:listing))

    assert_predicate plan, :ok?
    assert_empty plan.blocked_lines
  end

  test "an empty cart has nothing standing in the way" do
    plan = OrderPlacement.plan([])

    assert_predicate plan, :ok?
    assert_empty plan.blocked_lines
  end

  test "each reason reads as a sentence beside the piece it is about" do
    assert_equal "no longer available", OrderPlacement.notice_for(:removed)
    assert_equal "sold out", OrderPlacement.notice_for(:sold_out)
    assert_equal "no longer for sale", OrderPlacement.notice_for(:off_sale)
    assert_equal "no longer in stock in that quantity", OrderPlacement.notice_for(:short_stock)
  end

  test "the log payload names the listing, title, and reason of every blocked line" do
    blocked = OrderPlacement::BlockedLine.new(listing_id: "lst_1", title: "Harbour at dusk", reason: :sold_out)

    assert_equal(
      [ { listing_id: "lst_1", title: "Harbour at dusk", reason: :sold_out } ],
      OrderPlacement.log_payload([ blocked ])
    )
  end

  private

  def line(overrides = {})
    OrderPlacement::Line.new(**{
      listing_id: "lst_1", title: "Harbour at dusk", status: "for_sale",
      available_quantity: 1, quantity: 1, removed: false
    }.merge(overrides))
  end
end
