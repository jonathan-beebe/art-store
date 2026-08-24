module Shop
  class CartsController < BaseController
    def show
      @items = current_cart.items.includes(listing: :seller).order(:created_at, :id)
      @subtotal = current_cart.subtotal
    end
  end
end
