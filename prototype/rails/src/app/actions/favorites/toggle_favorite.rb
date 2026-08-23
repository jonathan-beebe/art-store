module Favorites
  class ToggleFavorite
    def call(customer:, listing:, now:)
      saved = customer.favorites.find_by(listing: listing)
      change = Domain::Shop::FavoriteChange.from_current_state(saved.present?)

      apply(change, customer, listing, saved)

      listing.record_event!(
        Domain::Shop::FavoriteChange.listing_event(change), customer_id: customer.id, at: now
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
