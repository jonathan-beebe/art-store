require_relative "../listings/listing_event_type"

module Domain
  module Shop
    # One button favorites and unfavorites, so what it does next follows from
    # what the visitor has saved already.
    module FavoriteChange
      ADDED = "added"
      REMOVED = "removed"

      ALL = [ADDED, REMOVED].freeze

      LISTING_EVENTS = {
        ADDED => Listings::ListingEventType::FAVORITE,
        REMOVED => Listings::ListingEventType::UNFAVORITE
      }.freeze

      module_function

      def from_current_state(favorited)
        favorited ? REMOVED : ADDED
      end

      def listing_event(change)
        LISTING_EVENTS.fetch(change)
      end

      def added?(change)
        change == ADDED
      end
    end
  end
end
