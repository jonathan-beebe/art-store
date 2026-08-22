require_relative "../listings/listing_event_type"

module Domain
  module Reports
    # What a listing's event log adds up to. An unfavorite is recorded as its
    # own event and leaves the favorites count where it was.
    ActivityTotals = Data.define(:views, :favorites, :cart_adds) do
      def self.from(counts_by_event_type)
        new(
          views: counts_by_event_type.fetch(Listings::ListingEventType::VIEW, 0),
          favorites: counts_by_event_type.fetch(Listings::ListingEventType::FAVORITE, 0),
          cart_adds: counts_by_event_type.fetch(Listings::ListingEventType::CART_ADD, 0)
        )
      end

      def total
        views + favorites + cart_adds
      end
    end
  end
end
