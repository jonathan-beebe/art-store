module Shop
  class ListingsController < BaseController
    def show
      @listing = on_storefront.includes(:seller).find_by!(slug: params[:slug])

      Listings::RecordListingEvent.new.call(
        listing: @listing,
        customer_id: current_customer.id,
        event_type: Domain::Listings::ListingEventType::VIEW,
        now: now
      )

      @purchasable = Domain::Listings::ListingAvailability.purchasable?(@listing.status, @listing.quantity)
      @favorited = current_customer.favorites.exists?(listing: @listing)
    end

    private

    def on_storefront
      Listing.where(status: Domain::Listings::ListingAvailability::ON_STOREFRONT)
    end
  end
end
