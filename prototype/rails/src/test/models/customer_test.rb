require "test_helper"

class CustomerTest < ActiveSupport::TestCase
  test "a customer with no address is anonymous" do
    assert_predicate create_anonymous_customer, :anonymous?
  end

  test "a customer holding an address is not anonymous" do
    refute_predicate create_verified_customer, :anonymous?
  end

  test "the address is normalized on the way in" do
    customer = create_verified_customer(email: "  Buyer@Example.COM ")

    assert_equal "buyer@example.com", customer.email
  end

  test "an anonymous customer keeps a null address rather than a blank one" do
    assert_nil create_anonymous_customer.email
  end

  test "several anonymous customers coexist under the unique address index" do
    create_anonymous_customer
    create_anonymous_customer

    assert_equal 2, Customer.count
  end

  test "verified lists only the customers holding an address" do
    verified = create_verified_customer
    create_anonymous_customer

    assert_equal [verified], Customer.verified.to_a
  end

  test "a visitor with no cookie and no account gets a new verified customer" do
    customer = Customer.claim("newcomer@example.com")

    assert_equal "newcomer@example.com", customer.email
    assert_predicate customer.email_verified_at, :present?
  end

  test "a visitor with no cookie signs in to the account holding the address" do
    existing = create_verified_customer

    assert_equal existing, Customer.claim(existing.email)
    assert_equal 1, Customer.count
  end

  test "an anonymous visitor with no account claims the anonymous row in place" do
    anonymous = create_anonymous_customer

    customer = Customer.claim("newcomer@example.com", current: anonymous)

    assert_equal anonymous, customer
    assert_equal "newcomer@example.com", customer.email
    assert_equal 1, Customer.count
  end

  test "claiming in place marks the address verified" do
    freeze_time do
      customer = Customer.claim("newcomer@example.com", current: create_anonymous_customer)

      assert_equal Time.current, customer.email_verified_at
    end
  end

  test "an anonymous visitor whose address already has an account merges into it" do
    anonymous = create_anonymous_customer
    existing = create_verified_customer

    assert_equal existing, Customer.claim(existing.email, current: anonymous)
    assert_equal existing, CustomerMerge.sole.customer
    assert_equal anonymous, CustomerMerge.sole.anonymous_customer
  end

  test "a cookie already pointing at the account writes no merge" do
    existing = create_verified_customer

    assert_equal existing, Customer.claim(existing.email, current: existing)
    assert_equal 0, CustomerMerge.count
  end

  test "an address left unverified by a guest checkout is settled by the link" do
    guest = Customer.create!(email: "guest@example.com")

    customer = Customer.claim("guest@example.com")

    assert_equal guest, customer
    assert_predicate customer.email_verified_at, :present?
  end

  test "an address differing only in case reaches the same account" do
    existing = create_verified_customer(email: "buyer@example.com")

    assert_equal existing, Customer.claim("BUYER@Example.com")
  end

  test "absorb moves the history of the anonymous customer" do
    anonymous = create_anonymous_customer
    verified = create_verified_customer
    listing = create_listing
    favorite = Favorite.create!(customer: anonymous, listing: listing)
    cart = Cart.create!(customer: anonymous)
    order = order_for(anonymous, listing)
    event = listing.events.create!(
      customer: anonymous, event_type: "view", occurred_at: Time.current
    )
    notification = Notification.create!(customer: anonymous, subject: "Order placed", body: "Order #1 is open.")

    verified.absorb(anonymous)

    assert_equal verified, favorite.reload.customer
    assert_equal verified, cart.reload.customer
    assert_equal verified, order.reload.customer
    assert_equal verified, event.reload.customer
    assert_equal verified, notification.reload.customer
  end

  test "absorb leaves the rows of a bystander where they are" do
    anonymous = create_anonymous_customer
    verified = create_verified_customer
    bystander = create_anonymous_customer
    favorite = Favorite.create!(customer: bystander, listing: create_listing)

    verified.absorb(anonymous)

    assert_equal bystander, favorite.reload.customer
  end

  test "absorb records the merge so a stale cookie still resolves" do
    anonymous = create_anonymous_customer
    verified = create_verified_customer

    assert_equal verified, verified.absorb(anonymous)
    assert_equal verified, CustomerMerge.sole.customer
    assert_equal anonymous, CustomerMerge.sole.anonymous_customer
  end

  test "absorb leaves the anonymous row in place for the merge trail" do
    anonymous = create_anonymous_customer

    create_verified_customer.absorb(anonymous)

    assert Customer.exists?(anonymous.id)
  end

  test "from_cookie finds the customer the cookie points at" do
    customer = create_anonymous_customer

    assert_equal customer, Customer.from_cookie(customer.id)
  end

  test "from_cookie follows a merge so a stale cookie lands on the verified customer" do
    anonymous = create_anonymous_customer
    verified = create_verified_customer
    CustomerMerge.create!(anonymous_customer: anonymous, customer: verified)

    assert_equal verified, Customer.from_cookie(anonymous.id)
  end

  test "from_cookie resolves a cookie carrying the id as a string" do
    customer = create_anonymous_customer

    assert_equal customer, Customer.from_cookie(customer.id.to_s)
  end

  test "from_cookie resolves nothing for a customer that no longer exists" do
    assert_nil Customer.from_cookie(404)
  end

  test "from_cookie resolves nothing when there is no cookie" do
    assert_nil Customer.from_cookie(nil)
  end

  test "from_cookie resolves nothing for a cookie that is not an id" do
    assert_nil Customer.from_cookie("../../etc/passwd")
  end

  test "from_cookie resolves nothing for a cookie holding a meaningless id" do
    assert_nil Customer.from_cookie(0)
  end

  test "a visitor with no cart gets one" do
    shopper = create_anonymous_customer

    cart = shopper.current_cart

    assert_equal shopper, cart.customer
    assert_empty cart.items
  end

  test "the visitor keeps the same cart on a second call" do
    shopper = create_anonymous_customer

    assert_equal shopper.current_cart, shopper.current_cart
  end

  test "a merged customer keeps shopping with the cart holding items" do
    shopper = create_verified_customer
    cart_for(shopper)
    filled = cart_holding(shopper, create_listing)

    assert_equal filled, shopper.current_cart
  end

  test "it saves a favorite and records the event" do
    shopper = create_verified_customer
    listing = create_listing

    assert_equal :added, shopper.toggle_favorite(listing, at: moment("2026-08-20 09:00:00"))
    assert shopper.favorited?(listing)
    assert_equal ["favorite"], listing.events.pluck(:event_type)
  end

  test "toggling twice drops the favorite and records the event" do
    shopper = create_verified_customer
    listing = create_listing
    shopper.toggle_favorite(listing, at: moment("2026-08-20 09:00:00"))

    assert_equal :removed, shopper.toggle_favorite(listing, at: moment("2026-08-20 09:05:00"))
    refute shopper.favorited?(listing)
    assert_equal %w[favorite unfavorite], listing.events.order(:occurred_at).pluck(:event_type)
  end

  test "it records the favorite against the visitor who saved it" do
    shopper = create_verified_customer
    listing = create_listing

    shopper.toggle_favorite(listing, at: moment("2026-08-20 09:00:00"))

    assert_equal shopper.id, listing.events.sole.customer_id
  end

  test "one visitor saving leaves another visitor alone" do
    shopper = create_verified_customer
    other = create_verified_customer
    listing = create_listing
    shopper.toggle_favorite(listing, at: moment("2026-08-20 09:00:00"))

    assert_equal :added, other.toggle_favorite(listing, at: moment("2026-08-20 09:01:00"))
    assert shopper.favorited?(listing)
  end
end
