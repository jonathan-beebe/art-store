require "test_helper"

class ToggleFavoriteTest < ActiveSupport::TestCase
  FavoriteChange = Domain::Shop::FavoriteChange

  setup do
    @toggle = Favorites::ToggleFavorite.new
    @shopper = create_verified_customer
    @listing = create_listing
  end

  test "it saves a favorite and records the event" do
    change = @toggle.call(customer: @shopper, listing: @listing, now: moment("2026-08-20 09:00:00"))

    assert_equal FavoriteChange::ADDED, change
    assert @shopper.favorites.exists?(listing: @listing)
    assert_equal ["favorite"], @listing.events.pluck(:event_type)
  end

  test "toggling twice drops the favorite and records the event" do
    @toggle.call(customer: @shopper, listing: @listing, now: moment("2026-08-20 09:00:00"))

    change = @toggle.call(customer: @shopper, listing: @listing, now: moment("2026-08-20 09:05:00"))

    assert_equal FavoriteChange::REMOVED, change
    refute @shopper.favorites.exists?(listing: @listing)
    assert_equal %w[favorite unfavorite], @listing.events.order(:occurred_at).pluck(:event_type)
  end

  test "it records the event against the visitor who saved it" do
    @toggle.call(customer: @shopper, listing: @listing, now: moment("2026-08-20 09:00:00"))

    assert_equal @shopper.id, @listing.events.sole.customer_id
  end

  test "one visitor saving leaves another visitor alone" do
    other = create_verified_customer
    @toggle.call(customer: @shopper, listing: @listing, now: moment("2026-08-20 09:00:00"))

    assert_equal FavoriteChange::ADDED,
      @toggle.call(customer: other, listing: @listing, now: moment("2026-08-20 09:01:00"))
    assert @shopper.favorites.exists?(listing: @listing)
  end
end
