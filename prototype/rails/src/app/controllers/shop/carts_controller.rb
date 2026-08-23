module Shop
  class CartsController < BaseController
    SOLD_OUT = "That listing is no longer for sale.".freeze

    def show
      @items = current_cart.items.includes(listing: :seller).order(:id)
      @totals = Domain::Cart::CartTotals.from(current_cart.lines)
    end

    def add
      listing = Listing.on_storefront.find_by!(slug: params[:slug])

      return redirect_to shop_listing_path(slug: listing.slug), alert: SOLD_OUT unless listing.purchasable?

      Carts::AddToCart.new.call(cart: current_cart, listing: listing, quantity: requested_quantity, now: now)

      redirect_to shop_cart_path
    end

    def remove
      Carts::RemoveFromCart.new.call(cart: current_cart, listing: Listing.on_storefront.find_by!(slug: params[:slug]))

      redirect_to shop_cart_path
    end

    private

    # The form offers a number field only for listings with more than one in
    # stock, so a request with nothing in it asks for one.
    def requested_quantity
      [params[:quantity].to_i, 1].max
    end
  end
end
