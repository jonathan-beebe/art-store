module Listings
  class RecordListingEvent
    def call(listing:, customer_id:, event_type:, now:)
      listing.listing_events.create!(customer_id: customer_id, event_type: event_type, occurred_at: now)
    end
  end
end
