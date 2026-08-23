require "test_helper"

module Listings
  class RecordListingEventTest < ActiveSupport::TestCase
    test "it records what happened and when" do
      art = create_listing

      event = RecordListingEvent.new.call(
        listing: art,
        customer_id: create_verified_customer.id,
        event_type: Domain::Listings::ListingEventType::VIEW,
        now: moment("2026-08-20 08:00:00")
      )

      assert_equal art, event.listing
      assert_equal Domain::Listings::ListingEventType::VIEW, event.event_type
      assert_equal moment("2026-08-20 08:00:00"), event.occurred_at
    end

    test "an anonymous visitor leaves an event with no customer" do
      event = RecordListingEvent.new.call(
        listing: create_listing,
        customer_id: nil,
        event_type: Domain::Listings::ListingEventType::VIEW,
        now: moment("2026-08-20 08:00:00")
      )

      assert_nil event.customer_id
    end
  end
end
