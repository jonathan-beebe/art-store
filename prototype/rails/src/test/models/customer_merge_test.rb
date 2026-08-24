require "test_helper"

# `Customer#absorb` applying `CustomerMergePlan` to real rows: the cart fold,
# the favorites fold, and the standing decisions the fold makes about what a
# merge does not touch.
class CustomerMergeTest < ActiveSupport::TestCase
  test "overlapping cart lines are summed into one" do
    listing = create_listing(quantity: 10)
    anonymous = create_anonymous_customer
    verified = create_verified_customer
    cart_holding(anonymous, listing)
    cart_holding(verified, listing)

    verified.absorb(anonymous)

    assert_equal 2, verified.current_cart.items.sole.quantity
  end

  test "a summed quantity over stock is clamped to what is left" do
    listing = create_listing(quantity: 3)
    anonymous = create_anonymous_customer
    verified = create_verified_customer
    cart_holding(anonymous, listing)
    verified.current_cart.add(listing, quantity: 2)

    verified.absorb(anonymous)

    assert_equal 3, verified.current_cart.items.sole.quantity
  end

  test "a line clamped to zero stock is dropped from the folded cart" do
    listing = create_listing(quantity: 1)
    anonymous = create_anonymous_customer
    verified = create_verified_customer
    cart_holding(anonymous, listing)
    verified.current_cart.add(listing)
    listing.update_column(:quantity, 0)

    verified.absorb(anonymous)

    assert_empty verified.current_cart.items
  end

  test "non-overlapping cart lines are both kept" do
    mine = create_listing(quantity: 5)
    theirs = create_listing(quantity: 5)
    anonymous = create_anonymous_customer
    verified = create_verified_customer
    cart_holding(anonymous, theirs)
    cart_holding(verified, mine)

    verified.absorb(anonymous)

    assert_equal [ mine, theirs ].map(&:id).sort, verified.current_cart.items.map(&:listing_id).sort
  end

  test "the anonymous cart is gone after the fold, not re-pointed" do
    listing = create_listing
    anonymous = create_anonymous_customer
    verified = create_verified_customer
    cart = cart_holding(anonymous, listing)

    verified.absorb(anonymous)

    refute Cart.exists?(cart.id)
    assert_equal 0, anonymous.carts.count
    assert_equal 1, verified.carts.count
  end

  test "a merge with no anonymous cart leaves the verified customer's cart untouched" do
    listing = create_listing
    anonymous = create_anonymous_customer
    verified = create_verified_customer
    cart_holding(verified, listing)

    verified.absorb(anonymous)

    assert_equal 1, verified.carts.count
    assert_equal listing, verified.current_cart.items.sole.listing
  end

  test "a merge with neither customer holding a cart creates none" do
    anonymous = create_anonymous_customer
    verified = create_verified_customer

    verified.absorb(anonymous)

    assert_equal 0, verified.carts.count
  end

  test "favorites union with no duplicates" do
    shared = create_listing
    only_anonymous = create_listing
    anonymous = create_anonymous_customer
    verified = create_verified_customer
    kept_shared = Favorite.create!(customer: verified, listing: shared)
    moved = Favorite.create!(customer: anonymous, listing: only_anonymous)
    Favorite.create!(customer: anonymous, listing: shared)
    total_before = Favorite.count

    verified.absorb(anonymous)

    assert_equal [ shared, only_anonymous ].map(&:id).sort, verified.favorites.pluck(:listing_id).sort
    assert_equal 0, anonymous.favorites.count
    # The union is reached by moving and dropping rows, never by inserting a
    # new one: the surviving rows are the exact same primary keys, not fresh
    # ones a duplicate-then-insert shape would mint.
    assert_equal [ kept_shared.id, moved.id ].sort, verified.favorites.map(&:id).sort
    assert_equal verified, moved.reload.customer
    assert_equal total_before - 1, Favorite.count
  end

  test "a duplicate favorite is dropped rather than reached through the unique index" do
    listing = create_listing
    anonymous = create_anonymous_customer
    verified = create_verified_customer
    dropped = Favorite.create!(customer: anonymous, listing: listing)
    kept = Favorite.create!(customer: verified, listing: listing)

    verified.absorb(anonymous)

    assert_equal [ kept ], verified.favorites.to_a
    refute Favorite.exists?(dropped.id)
  end

  test "the favorites fold moves and drops rows, it never inserts one" do
    shared = create_listing
    only_anonymous = create_listing
    anonymous = create_anonymous_customer
    verified = create_verified_customer
    Favorite.create!(customer: verified, listing: shared)
    Favorite.create!(customer: anonymous, listing: only_anonymous)
    Favorite.create!(customer: anonymous, listing: shared)

    query_names = []
    subscription = ActiveSupport::Notifications.subscribe("sql.active_record") do |*, payload|
      query_names << payload[:name]
    end
    begin
      verified.absorb(anonymous)
    ensure
      ActiveSupport::Notifications.unsubscribe(subscription)
    end

    refute_includes query_names, "Favorite Create"
  end

  test "a block on the anonymous row is not re-pointed by a merge" do
    admin = create_admin
    anonymous = create_anonymous_customer
    verified = create_verified_customer
    block = anonymous.block!(reason: "Chargeback fraud.", by: admin)

    verified.absorb(anonymous)

    assert_equal anonymous, block.reload.customer
    refute_predicate verified, :blocked?
    assert_predicate anonymous, :blocked?
  end

  test "the merge is written inside one transaction: a failure mid-fold leaves nothing changed" do
    listing = create_listing
    anonymous = create_anonymous_customer
    verified = create_verified_customer
    cart_holding(anonymous, listing)
    Favorite.create!(customer: anonymous, listing: listing)
    # A row already claims this anonymous customer, so the merge ledger insert
    # `fold` ends with trips the unique index and the transaction rolls back.
    CustomerMerge.create!(anonymous_customer: anonymous, customer: create_verified_customer)

    assert_raises(ActiveRecord::RecordNotUnique) { verified.absorb(anonymous) }

    assert_equal 1, anonymous.carts.count
    assert_equal 1, anonymous.favorites.count
    assert_equal 1, CustomerMerge.count
  end

  test "the customer.merge log line names both customers" do
    anonymous = create_anonymous_customer
    verified = create_verified_customer

    lines = captured_log_lines { verified.absorb(anonymous) }

    merging = log_lines_for("customer.merge", lines)
    assert_equal [ "will", "did" ], merging.map { |line| line["phase"] }
    assert_equal [ verified.id ], merging.map { |line| line["data"]["customer_id"] }.uniq
    assert_equal [ anonymous.id ], merging.map { |line| line["data"]["anonymous_customer_id"] }.uniq
  end
end
