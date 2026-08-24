require "test_helper"

# What the log says, read back the way anyone debugging reads it: as JSON
# lines. The payload and the vocabulary are fixed by docs/alignment.md §2, so
# these assertions are the contract with the other two prototypes.
class LoggingTest < ActionDispatch::IntegrationTest
  ISO_8601_UTC = /\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z\z/
  LEVELS = %w[debug info warn error].freeze
  PHASES = %w[will doing did refused failed].freeze
  BUYER = "logged-buyer@example.test".freeze

  test "every line carries the payload the three prototypes share" do
    listing = create_listing(status: :for_sale, quantity: 2)

    lines = captured_log_lines { get shop_listing_path(slug: listing.slug) }

    lines.each do |line|
      assert_match ISO_8601_UTC, line["ts"]
      assert_includes LEVELS, line["level"]
      assert_includes PHASES, line["phase"]
      assert_kind_of String, line["event"]
      assert_predicate line["msg"], :present?
      assert_equal line["request_id"], PrefixedUlid.parse(line["request_id"], :req)
      assert_equal line["session_id"], PrefixedUlid.parse(line["session_id"], :ses)
      assert_equal "customer", line["actor_type"]
      assert_equal line["actor_id"], PrefixedUlid.parse(line["actor_id"], :cus)
    end

    opening, closing = log_lines_for("http.request", lines)
    assert_equal [ "will", "did" ], [ opening["phase"], closing["phase"] ]
    assert_equal({ "method" => "GET", "path" => shop_listing_path(slug: listing.slug) }, opening["data"])
    assert_nil opening["duration_ms"]
    assert_equal 200, closing["data"]["status"]
    assert_kind_of Integer, closing["duration_ms"]
    assert_nil closing["txn_id"]

    viewing = log_lines_for("listing.view", lines)
    assert_equal [ "will", "did" ], viewing.map { |line| line["phase"] }
    assert_equal 1, viewing.map { |line| line["txn_id"] }.uniq.size
    assert_equal viewing.first["txn_id"], PrefixedUlid.parse(viewing.first["txn_id"], :txn)
    assert_equal listing.id, viewing.last["data"]["listing_id"]
  end

  test "one checkout reads as one story under one request and one transaction" do
    listing = create_listing(status: :for_sale, quantity: 1, price_cents: 12_000)
    sign_in_as_customer(email: BUYER)
    post shop_add_to_cart_path(slug: listing.slug)

    lines = captured_log_lines { post shop_place_order_path, params: checkout_params }

    assert_equal [
      "http.request will",
      "order.place will",
      "order.place did",
      "order.pay will",
      "ledger.write will",
      "ledger.write did",
      "notification.write will",
      "notification.deliver will",
      "notification.deliver did",
      "notification.write did",
      "order.pay did",
      "http.request did"
    ], log_story(lines)

    assert_equal 1, lines.map { |line| line["request_id"] }.uniq.size

    placing = log_lines_for("order.place", lines)
    assert_equal 1, placing.map { |line| line["txn_id"] }.uniq.size
    assert_equal 12_000, placing.last["data"]["total_cents"]
    assert_equal "awaiting_payment", placing.last["data"]["status"]
    assert_equal 1, placing.last["data"]["fulfillment_ids"].size

    # Everything the charge wrote belongs to the charge's unit of work.
    charging = lines.reject { |line| line["event"] == "http.request" || line["event"] == "order.place" }
    assert_equal 1, charging.map { |line| line["txn_id"] }.uniq.size
    assert_equal "debug", log_lines_for("ledger.write", lines).first["level"]
  end

  test "the request id offered by the caller is echoed back" do
    get root_path, headers: { "X-Request-Id" => "trace-42_ABC" }

    assert_equal "trace-42_ABC", response.headers["X-Request-Id"]
  end

  test "a request id that is not the agreed shape is replaced" do
    offered = "not an id! #{'x' * 80}"

    lines = captured_log_lines { get root_path, headers: { "X-Request-Id" => offered } }

    minted = response.headers["X-Request-Id"]
    assert_equal minted, PrefixedUlid.parse(minted, :req)
    assert_equal [ minted ], lines.map { |line| line["request_id"] }.uniq
  end

  test "the browser keeps one session id through signing in and out" do
    get root_path
    minted = cookies[:sid]
    assert_equal minted, PrefixedUlid.parse(minted, :ses)

    get root_path
    assert_equal minted, cookies[:sid]
    assert_not_includes response.headers["Set-Cookie"].to_s, "sid="

    sign_in_as_customer(email: BUYER)
    assert_equal minted, cookies[:sid]

    post customer_logout_path
    assert_equal minted, cookies[:sid]

    lines = captured_log_lines { get root_path }
    assert_equal [ minted ], lines.map { |line| line["session_id"] }.uniq
  end

  test "a declined card is refused at info and leaves the world where it was" do
    listing = create_listing(status: :for_sale, quantity: 1)
    sign_in_as_customer(email: BUYER)
    post shop_add_to_cart_path(slug: listing.slug)

    lines = captured_log_lines do
      post shop_place_order_path, params: checkout_params(card_number: TestRecords::DECLINED_CARD)
    end

    refusal = log_lines_for("order.pay", lines).last
    assert_equal "refused", refusal["phase"]
    assert_equal "info", refusal["level"]
    assert_equal "generic_decline", refusal["data"]["decline_reason"]
    assert_equal "payment_failed", order_of_visiting_customer.status
    assert_empty log_lines_for("ledger.write", lines)
  end

  test "an exception nobody planned for is told as a failure at error" do
    cart = cart_for(create_anonymous_customer)
    sold_out = create_listing(status: :for_sale, quantity: 0)

    lines = captured_log_lines { assert_raises(ArgumentError) { cart.add(sold_out) } }

    failure = log_lines_for("cart.add", lines).last
    assert_equal "failed", failure["phase"]
    assert_equal "error", failure["level"]
    assert_equal "ArgumentError", failure["error"]["type"]
    assert_equal "that listing is sold out", failure["error"]["message"]
    # A stack is written in development only.
    assert_nil failure["error"]["stack"]
  end

  test "no email address reaches a log line" do
    listing = create_listing(status: :for_sale, quantity: 1)

    lines = captured_log_lines do
      sign_in_as_customer(email: BUYER)
      post shop_add_to_cart_path(slug: listing.slug)
      post shop_place_order_path, params: checkout_params
      post shop_conversation_messages_path(open_a_thread(listing)), params: { message: { body: "Hello" } }
    end

    assert_predicate lines, :any?
    lines.each { |line| assert_not_includes line.to_json, BUYER }
    assert_not_includes lines.to_json, "@"
  end

  private

  def checkout_params(card_number: TestRecords::APPROVED_CARD)
    { card_number: card_number }.merge(shipping_params)
  end

  def open_a_thread(listing)
    post shop_listing_questions_path(slug: listing.slug), params: { message: { body: "Is it framed?" } }

    Conversation.sole
  end
end
