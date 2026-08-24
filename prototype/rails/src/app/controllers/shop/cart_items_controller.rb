module Shop
  class CartItemsController < BaseController
    SOLD_OUT = "That listing is no longer for sale.".freeze

    before_action :set_listing

    def create
      return redirect_to shop_listing_path(slug: @listing.slug), alert: SOLD_OUT unless @listing.purchasable?

      current_cart.add(@listing, quantity: requested_quantity)

      redirect_to shop_cart_path
    end

    def destroy
      current_cart.remove(@listing)

      redirect_to shop_cart_path, status: :see_other
    end

    private

    def set_listing
      @listing = Listing.on_storefront.find_by!(slug: params[:slug])
    end

    # The form offers a number field only for listings with more than one in
    # stock, so a request with nothing in it asks for one.
    def requested_quantity
      [ params[:quantity].to_i, 1 ].max
    end
  end
end
