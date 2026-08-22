module Carts
  class RemoveFromCart
    def call(cart:, listing:)
      cart.items.where(listing: listing).destroy_all

      cart
    end
  end
end
