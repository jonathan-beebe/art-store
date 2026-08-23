module Shop
  class FavoritesController < BaseController
    def index
      @listings = Listing
        .where(id: current_customer.favorites.select(:listing_id))
        .includes(:seller)
        .order(id: :desc)
    end

    def toggle
      listing = Listing.on_storefront.find_by!(slug: params[:slug])

      current_customer.toggle_favorite(listing, at: now)

      redirect_back fallback_location: shop_listing_path(slug: listing.slug)
    end
  end
end
