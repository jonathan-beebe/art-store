module Favorites
  class ToggleFavorite
    def initialize(record_listing_event: Listings::RecordListingEvent.new)
      @record_listing_event = record_listing_event
    end

    def call(customer:, listing:, now:)
      saved = customer.favorites.find_by(listing: listing)
      change = Domain::Shop::FavoriteChange.from_current_state(saved.present?)

      apply(change, customer, listing, saved)

      @record_listing_event.call(
        listing: listing,
        customer_id: customer.id,
        event_type: Domain::Shop::FavoriteChange.listing_event(change),
        now: now
      )

      change
    end

    private

    def apply(change, customer, listing, saved)
      return customer.favorites.create!(listing: listing) if Domain::Shop::FavoriteChange.added?(change)

      saved.destroy!
    end
  end
end
