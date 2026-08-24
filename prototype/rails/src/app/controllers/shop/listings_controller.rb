module Shop
  class ListingsController < BaseController
    def show
      @listing = Listing.on_storefront.includes(:seller).find_by!(slug: params[:slug])

      Story.tell("listing.view", "showing a listing to a visitor", listing_id: @listing.id) do |story|
        @listing.record_event!("view", customer_id: current_customer.id)

        story.did("showed the listing", listing_id: @listing.id, slug: @listing.slug)
      end

      @purchasable = @listing.purchasable?
      @favorited = current_customer.favorited?(@listing)
      @faqs = @listing.faqs.oldest_first
    end
  end
end
