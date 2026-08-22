require "test_helper"

class FavoriteChangeTest < ActiveSupport::TestCase
  FavoriteChange = Domain::Shop::FavoriteChange
  EventType = Domain::Listings::ListingEventType

  test "a listing nobody saved gets favorited" do
    assert_equal FavoriteChange::ADDED, FavoriteChange.from_current_state(false)
  end

  test "a saved listing gets dropped" do
    assert_equal FavoriteChange::REMOVED, FavoriteChange.from_current_state(true)
  end

  test "each change records its own event" do
    assert_equal EventType::FAVORITE, FavoriteChange.listing_event(FavoriteChange::ADDED)
    assert_equal EventType::UNFAVORITE, FavoriteChange.listing_event(FavoriteChange::REMOVED)
  end

  test "an unknown change records nothing" do
    assert_raises(KeyError) { FavoriteChange.listing_event("saved") }
  end

  test "only the added change reads as added" do
    assert FavoriteChange.added?(FavoriteChange::ADDED)
    refute FavoriteChange.added?(FavoriteChange::REMOVED)
  end
end
