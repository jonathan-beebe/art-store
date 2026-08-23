module Carts
  class AddToCart
    def call(cart:, listing:, quantity:, now:)
      item = cart.items.find_or_initialize_by(listing: listing)
      item.update!(quantity: Domain::Cart::CartQuantity.within_stock(
        requested: item.quantity.to_i + quantity, available: listing.quantity
      ))

      listing.record_event!("cart_add", customer_id: cart.customer_id, at: now)

      item
    end
  end
end
