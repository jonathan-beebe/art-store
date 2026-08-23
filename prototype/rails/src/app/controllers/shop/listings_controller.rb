module Shop
  class ListingsController < BaseController
    def show
      @listing = Listing.on_storefront.includes(:seller).find_by!(slug: params[:slug])

      @listing.record_event!("view", customer_id: current_customer.id)

      @purchasable = @listing.purchasable?
      @favorited = current_customer.favorited?(@listing)
    end
  end
end
