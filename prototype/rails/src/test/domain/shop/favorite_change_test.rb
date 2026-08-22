require "test_helper"

class FavoriteChangeTest < ActiveSupport::TestCase
  FavoriteChange = Domain::Shop::FavoriteChange
  EventType = Domain::Listings::ListingEventType

  def test_a_listing_nobody_saved_gets_favorited
    assert_equal FavoriteChange::ADDED, FavoriteChange.from_current_state(false)
  end

  def test_a_saved_listing_gets_dropped
    assert_equal FavoriteChange::REMOVED, FavoriteChange.from_current_state(true)
  end

  def test_each_change_records_its_own_event
    assert_equal EventType::FAVORITE, FavoriteChange.listing_event(FavoriteChange::ADDED)
    assert_equal EventType::UNFAVORITE, FavoriteChange.listing_event(FavoriteChange::REMOVED)
  end

  def test_an_unknown_change_records_nothing
    assert_raises(KeyError) { FavoriteChange.listing_event("saved") }
  end

  def test_only_the_added_change_reads_as_added
    assert FavoriteChange.added?(FavoriteChange::ADDED)
    refute FavoriteChange.added?(FavoriteChange::REMOVED)
  end
end
