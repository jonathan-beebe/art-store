require "test_helper"

# The seven limits `docs/alignment.md` §3 names, driven over HTTP so each
# test exercises the whole path: `RateLimits::CONFIG`, the `rate_limit`
# macro or `rate_limit_trip!`, the 429 response, and the `rate_limit.exceed`
# log line. Each limit's own count and window come from `RateLimits.fetch`
# rather than a hard-coded number, so a default changing in
# `config/initializers/rate_limits.rb` moves these tests with it.
class RateLimitingTest < ActionDispatch::IntegrationTest
  test "magic_link_request trips on the address, independently of the ip" do
    limit = RateLimits.fetch(:magic_link_request)

    limit.count.times do |i|
      post seller_send_magic_link_path, params: { email: "artist@example.com" },
        env: { "REMOTE_ADDR" => "10.0.0.#{i}" }
      assert_response :redirect
    end

    post seller_send_magic_link_path, params: { email: "artist@example.com" },
      env: { "REMOTE_ADDR" => "10.0.0.#{limit.count}" }

    assert_response :too_many_requests
    assert_retry_after_within(limit)
    assert_equal limit.count, MagicLink.count
  end

  test "magic_link_request trips on the ip, independently of the address" do
    limit = RateLimits.fetch(:magic_link_request)

    limit.count.times do |i|
      post customer_send_magic_link_path, params: { email: "buyer-#{i}@example.com" },
        env: { "REMOTE_ADDR" => "10.0.1.1" }
      assert_response :redirect
    end

    post customer_send_magic_link_path, params: { email: "buyer-tripped@example.com" },
      env: { "REMOTE_ADDR" => "10.0.1.1" }

    assert_response :too_many_requests
    assert_equal limit.count, MagicLink.count
  end

  test "magic_link_request re-renders the sign-in form with the sentence as a field-less error" do
    limit = RateLimits.fetch(:magic_link_request)
    trip_seller_magic_link_request(limit.count, ip: "10.0.2.1")

    assert_select "body[data-site=?]", "seller"
    assert_select "form[action=?]", seller_send_magic_link_path
    assert_select "[role=alert]", text: /Too many requests — try again in \d+ minutes?\./
  end

  test "magic_link_request trips on the admin sign-in form too" do
    limit = RateLimits.fetch(:magic_link_request)
    admin = create_admin

    limit.count.times do
      post admin_send_magic_link_path, params: { email: admin.email }, env: { "REMOTE_ADDR" => "10.0.9.1" }
      assert_response :redirect
    end

    post admin_send_magic_link_path, params: { email: admin.email }, env: { "REMOTE_ADDR" => "10.0.9.1" }

    assert_response :too_many_requests
    assert_select "body[data-site=?]", "admin"
    assert_select "[role=alert]", text: /Too many requests/
  end

  test "magic_link_request resets once its window passes" do
    limit = RateLimits.fetch(:magic_link_request)

    travel_to Time.current do
      trip_seller_magic_link_request(limit.count, ip: "10.0.3.1")
    end

    travel_to limit.window_seconds.seconds.from_now do
      post seller_send_magic_link_path, params: { email: "artist@example.com" },
        env: { "REMOTE_ADDR" => "10.0.3.1" }

      assert_response :redirect
    end
  end

  test "guest checkout's implicit magic link trips the same limit, on the checkout form" do
    limit = RateLimits.fetch(:magic_link_request)
    listing = create_listing(status: :for_sale, quantity: limit.count + 1)
    ip = "10.0.4.1"

    limit.count.times do |i|
      post shop_add_to_cart_path(slug: listing.slug), env: { "REMOTE_ADDR" => ip }
      post shop_place_order_path, params: checkout_params(email: "guest-#{i}@example.com"), env: { "REMOTE_ADDR" => ip }
      assert_response :redirect
    end

    post shop_add_to_cart_path(slug: listing.slug), env: { "REMOTE_ADDR" => ip }
    post shop_place_order_path, params: checkout_params(email: "guest-tripped@example.com"), env: { "REMOTE_ADDR" => ip }

    assert_response :too_many_requests
    assert_select "body[data-site=?]", "shop"
    assert_select "[role=alert]", text: /Too many requests/
  end

  test "magic_link_consume trips on the ip and answers the storefront's own page" do
    limit = RateLimits.fetch(:magic_link_consume)

    limit.count.times { get verify_magic_link_path("0" * 64) }

    get verify_magic_link_path("0" * 64)

    assert_response :too_many_requests
    assert_retry_after_within(limit)
    assert_select "body[data-site=?]", "shop"
    assert_select "h1", "Too many requests"
  end

  test "magic_link_consume resets once its window passes" do
    limit = RateLimits.fetch(:magic_link_consume)

    travel_to Time.current do
      limit.count.times { get verify_magic_link_path("0" * 64) }
      get verify_magic_link_path("0" * 64)
      assert_response :too_many_requests
    end

    travel_to limit.window_seconds.seconds.from_now do
      get verify_magic_link_path("0" * 64)

      assert_response :redirect
    end
  end

  test "message_post trips on the actor and re-renders the thread" do
    limit = RateLimits.fetch(:message_post)
    seller = create_seller
    listing = create_listing(seller)
    sign_in_as_customer(email: "asker@example.com")
    post shop_listing_questions_path(slug: listing.slug), params: { message: { body: "Opening question" } }
    conversation = Conversation.sole
    before = Message.count

    limit.count.times do |i|
      post shop_conversation_messages_path(conversation), params: { message: { body: "reply #{i}" } }
      assert_response :redirect
    end

    post shop_conversation_messages_path(conversation), params: { message: { body: "one too many" } }

    assert_response :too_many_requests
    assert_select "[role=alert]", text: /Too many requests/
    assert_equal before + limit.count, Message.count
  end

  test "message_post resets once its window passes" do
    limit = RateLimits.fetch(:message_post)
    seller = create_seller
    listing = create_listing(seller)
    sign_in_as_customer(email: "asker2@example.com")
    post shop_listing_questions_path(slug: listing.slug), params: { message: { body: "Opening question" } }
    conversation = Conversation.sole

    travel_to Time.current do
      limit.count.times { post shop_conversation_messages_path(conversation), params: { message: { body: "hi" } } }
      post shop_conversation_messages_path(conversation), params: { message: { body: "hi" } }
      assert_response :too_many_requests
    end

    travel_to limit.window_seconds.seconds.from_now do
      post shop_conversation_messages_path(conversation), params: { message: { body: "hi again" } }

      assert_response :redirect
    end
  end

  test "conversation_open trips on the actor across the routes it guards" do
    limit = RateLimits.fetch(:conversation_open)
    seller = create_seller
    sign_in_as_customer(email: "opener@example.com")

    (limit.count - 1).times do |i|
      listing = create_listing(seller)
      post shop_listing_questions_path(slug: listing.slug), params: { message: { body: "Question #{i}" } }
      assert_response :redirect
    end
    post shop_support_path
    assert_response :redirect

    listing = create_listing(seller)
    before = Conversation.count
    post shop_listing_questions_path(slug: listing.slug), params: { message: { body: "One too many" } }

    assert_response :too_many_requests
    assert_select "h1", "Too many requests"
    assert_equal before, Conversation.count
  end

  test "conversation_open resets once its window passes" do
    limit = RateLimits.fetch(:conversation_open)
    seller = create_seller
    sign_in_as_seller

    travel_to Time.current do
      limit.count.times { post seller_support_path }
      post seller_support_path
      assert_response :too_many_requests
    end

    travel_to limit.window_seconds.seconds.from_now do
      post seller_support_path

      assert_response :redirect
    end
  end

  test "checkout trips on the customer and re-renders the checkout form" do
    limit = RateLimits.fetch(:checkout)
    sign_in_as_customer(email: "shopper@example.com")

    limit.count.times do
      post shop_place_order_path
      assert_response :redirect
    end

    post shop_place_order_path

    assert_response :too_many_requests
    assert_select "body[data-site=?]", "shop"
    assert_select "[role=alert]", text: /Too many requests/
  end

  test "checkout resets once its window passes" do
    limit = RateLimits.fetch(:checkout)
    sign_in_as_customer(email: "shopper2@example.com")

    travel_to Time.current do
      limit.count.times { post shop_place_order_path }
      post shop_place_order_path
      assert_response :too_many_requests
    end

    travel_to limit.window_seconds.seconds.from_now do
      post shop_place_order_path

      assert_response :redirect
    end
  end

  test "checkout no side effect on a trip" do
    limit = RateLimits.fetch(:checkout)
    sign_in_as_customer(email: "shopper3@example.com")
    limit.count.times { post shop_place_order_path }
    before = Order.count

    post shop_place_order_path

    assert_response :too_many_requests
    assert_equal before, Order.count
  end

  test "payment_attempt trips on the order and re-renders the card form" do
    limit = RateLimits.fetch(:payment_attempt)
    customer = create_verified_customer(email: "carduser@example.com")
    order = order_for(customer, create_listing)
    sign_in_as_customer(email: customer.email)
    before = Payment.count

    limit.count.times do
      post shop_pay_order_path(order), params: { card_number: TestRecords::DECLINED_CARD }
      assert_response :redirect
    end

    post shop_pay_order_path(order), params: { card_number: TestRecords::DECLINED_CARD }

    assert_response :too_many_requests
    assert_select "body[data-site=?]", "shop"
    assert_select "[role=alert]", text: /Too many requests/
    assert_equal before + limit.count, Payment.count
  end

  test "payment_attempt resets once its window passes" do
    limit = RateLimits.fetch(:payment_attempt)
    customer = create_verified_customer(email: "carduser2@example.com")
    order = order_for(customer, create_listing)
    sign_in_as_customer(email: customer.email)

    travel_to Time.current do
      limit.count.times { post shop_pay_order_path(order), params: { card_number: TestRecords::DECLINED_CARD } }
      post shop_pay_order_path(order), params: { card_number: TestRecords::DECLINED_CARD }
      assert_response :too_many_requests
    end

    travel_to limit.window_seconds.seconds.from_now do
      post shop_pay_order_path(order), params: { card_number: TestRecords::DECLINED_CARD }

      assert_response :redirect
    end
  end

  test "listing_write trips on the seller and re-renders the listing form" do
    limit = RateLimits.fetch(:listing_write)
    sign_in_as_seller
    before = Listing.count

    limit.count.times do
      post seller_listings_path, params: { listing: listing_write_params }
      assert_response :redirect
    end

    post seller_listings_path, params: { listing: listing_write_params }

    assert_response :too_many_requests
    assert_select "form[action=?]", seller_listings_path
    assert_select "[role=alert]", text: /Too many requests/
    assert_equal before + limit.count, Listing.count
  end

  test "listing_write resets once its window passes" do
    limit = RateLimits.fetch(:listing_write)
    sign_in_as_seller

    travel_to Time.current do
      limit.count.times { post seller_listings_path, params: { listing: listing_write_params } }
      post seller_listings_path, params: { listing: listing_write_params }
      assert_response :too_many_requests
    end

    travel_to limit.window_seconds.seconds.from_now do
      post seller_listings_path, params: { listing: listing_write_params }

      assert_response :redirect
    end
  end

  test "a trip writes one rate_limit.exceed line at warn, with the limit, key, and retry_after_seconds" do
    limit = RateLimits.fetch(:magic_link_consume)

    lines = captured_log_lines do
      limit.count.times { get verify_magic_link_path("0" * 64) }
      get verify_magic_link_path("0" * 64)
    end

    line = log_lines_for("rate_limit.exceed", lines).sole
    assert_equal "warn", line["level"]
    assert_equal "magic_link_consume", line["data"]["limit"]
    assert_kind_of String, line["data"]["key"]
    assert_kind_of Integer, line["data"]["retry_after_seconds"]
    assert_operator line["data"]["retry_after_seconds"], :<=, limit.window_seconds
  end

  test "an email-keyed trip's log line does not carry the address" do
    limit = RateLimits.fetch(:magic_link_request)

    lines = captured_log_lines do
      trip_seller_magic_link_request(limit.count, ip: "10.0.5.1", email: "secret@example.com")
    end

    line = log_lines_for("rate_limit.exceed", lines).sole
    assert_equal "[FILTERED]", line["data"]["key"]
  end

  test "an id-keyed trip's log line carries the raw key" do
    limit = RateLimits.fetch(:checkout)
    sign_in_as_customer(email: "loggedshopper@example.com")
    customer_id = signed_cookie(CustomerIdentity::COOKIE)

    lines = captured_log_lines do
      limit.count.times { post shop_place_order_path }
      post shop_place_order_path
    end

    line = log_lines_for("rate_limit.exceed", lines).sole
    assert_equal customer_id, line["data"]["key"]
  end

  test "the client ip is the socket's, ignoring a forwarded-for header, unless TRUSTED_PROXIES is set" do
    limit = RateLimits.fetch(:magic_link_consume)

    limit.count.times do |i|
      get verify_magic_link_path("0" * 64), headers: { "X-Forwarded-For" => "203.0.113.#{i}" }
    end
    get verify_magic_link_path("0" * 64), headers: { "X-Forwarded-For" => "203.0.113.99" }

    assert_response :too_many_requests
  end

  test "TRUSTED_PROXIES set, the first trusted proxy header decides the ip instead" do
    limit = RateLimits.fetch(:magic_link_consume)
    original = ENV["TRUSTED_PROXIES"]
    ENV["TRUSTED_PROXIES"] = "127.0.0.1/32"
    Rails.application.config.action_dispatch.trusted_proxies =
      [ IPAddr.new("127.0.0.1/32") ]

    limit.count.times do |i|
      get verify_magic_link_path("0" * 64), headers: { "X-Forwarded-For" => "203.0.113.#{i}" }
      assert_response :redirect
    end
    get verify_magic_link_path("0" * 64), headers: { "X-Forwarded-For" => "203.0.113.new" }

    assert_response :redirect
  ensure
    ENV["TRUSTED_PROXIES"] = original
  end

  private

  # Retry-After counts down to the window's own end, not the window's full
  # length, so it lands somewhere in (0, window_seconds] depending on when in
  # the window the trip fell.
  def assert_retry_after_within(limit)
    seconds = response.headers["Retry-After"].to_i

    assert_operator seconds, :>, 0
    assert_operator seconds, :<=, limit.window_seconds
  end

  def trip_seller_magic_link_request(count, ip:, email: "artist@example.com")
    count.times { post seller_send_magic_link_path, params: { email: email }, env: { "REMOTE_ADDR" => ip } }
    post seller_send_magic_link_path, params: { email: email }, env: { "REMOTE_ADDR" => ip }
  end

  def checkout_params(email:)
    { email: email }.merge(shipping_params)
  end

  def listing_write_params
    {
      title: "Harbour at Dusk", description: "An oil study of the harbour after sundown.",
      medium: "Oil on canvas", dimensions: "40 x 60 cm", price: "450.00", quantity: 1
    }
  end
end
