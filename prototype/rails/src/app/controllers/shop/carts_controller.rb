module Shop
  class CartsController < BaseController
    def show
      @items = current_cart.items.includes(listing: :seller).order(:created_at, :id)
      @subtotal = current_cart.subtotal
      @blocked_reasons = OrderPlacement.plan(@items).blocked_lines.index_by(&:listing_id).transform_values(&:reason)
    end
  end
end
