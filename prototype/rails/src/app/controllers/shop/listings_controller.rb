module Shop
  class ListingsController < BaseController
    def show
      @listing = Listing.on_storefront.includes(:seller).find_by!(slug: params[:slug])

      @listing.record_event!("view", customer_id: current_customer.id, at: now)

      @purchasable = @listing.purchasable?
      @favorited = current_customer.favorites.exists?(listing: @listing)
    end
  end
end
