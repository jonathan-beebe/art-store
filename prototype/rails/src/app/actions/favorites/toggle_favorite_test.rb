require "commerce_test_case"

class ToggleFavoriteTest < CommerceTestCase
  FavoriteChange = Domain::Shop::FavoriteChange

  def setup
    @toggle = Favorites::ToggleFavorite.new
    @shopper = customer
    @listing = listing(seller)
  end

  def test_it_saves_a_favorite_and_records_the_event
    change = @toggle.call(customer: @shopper, listing: @listing, now: moment("2026-08-20 09:00:00"))

    assert_equal FavoriteChange::ADDED, change
    assert @shopper.favorites.exists?(listing: @listing)
    assert_equal ["favorite"], @listing.listing_events.pluck(:event_type)
  end

  def test_toggling_twice_drops_the_favorite_and_records_the_event
    @toggle.call(customer: @shopper, listing: @listing, now: moment("2026-08-20 09:00:00"))

    change = @toggle.call(customer: @shopper, listing: @listing, now: moment("2026-08-20 09:05:00"))

    assert_equal FavoriteChange::REMOVED, change
    refute @shopper.favorites.exists?(listing: @listing)
    assert_equal %w[favorite unfavorite], @listing.listing_events.order(:occurred_at).pluck(:event_type)
  end

  def test_it_records_the_event_against_the_visitor_who_saved_it
    @toggle.call(customer: @shopper, listing: @listing, now: moment("2026-08-20 09:00:00"))

    assert_equal @shopper.id, @listing.listing_events.sole.customer_id
  end

  def test_one_visitor_saving_leaves_another_visitor_alone
    other = customer
    @toggle.call(customer: @shopper, listing: @listing, now: moment("2026-08-20 09:00:00"))

    assert_equal FavoriteChange::ADDED,
      @toggle.call(customer: other, listing: @listing, now: moment("2026-08-20 09:01:00"))
    assert @shopper.favorites.exists?(listing: @listing)
  end
end
