require "test_helper"

class ListingEventTest < ActiveSupport::TestCase
  test "totals read the count of each event a report shows" do
    totals = ListingEvent::Totals.from({ "view" => 12, "favorite" => 3, "cart_add" => 2 })

    assert_equal 12, totals.views
    assert_equal 3, totals.favorites
    assert_equal 2, totals.cart_adds
  end

  test "an event that has not happened counts zero" do
    totals = ListingEvent::Totals.from({ "view" => 12 })

    assert_equal 0, totals.favorites
    assert_equal 0, totals.cart_adds
  end

  test "totals ignore events no report shows" do
    assert_equal 0, ListingEvent::Totals.from({ "unfavorite" => 5 }).total
  end

  test "the total sums the three event kinds" do
    assert_equal 17, ListingEvent::Totals.from({ "view" => 12, "favorite" => 3, "cart_add" => 2 }).total
  end

  test "a listing nobody has seen totals zero" do
    assert_equal 0, ListingEvent::Totals.from({}).total
  end

  test "a day labels itself for a table row" do
    day = ListingEvent::Day.new(date: Date.new(2026, 8, 9), totals: ListingEvent::Totals.from({}))

    assert_equal "Aug 9", day.label
  end

  test "a day carries the totals of its date" do
    day = ListingEvent::Day.new(date: Date.new(2026, 8, 9), totals: ListingEvent::Totals.from({ "view" => 3 }))

    assert_equal 3, day.totals.views
  end

  test "totals by listing count each listing's own events" do
    seller = create_seller
    watched = create_listing(seller)
    ignored = create_listing(seller)
    watched.record_event!("view")
    watched.record_event!("favorite")
    ignored.record_event!("view")

    totals = ListingEvent.totals_by_listing([ watched, ignored ])

    assert_equal 1, totals[watched].views
    assert_equal 1, totals[watched].favorites
    assert_equal 0, totals[ignored].favorites
  end

  test "a listing with no events totals zero" do
    listing = create_listing

    assert_equal 0, ListingEvent.totals_by_listing([ listing ])[listing].total
  end
end
