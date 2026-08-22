module Carts
  class AddToCart
    def initialize(record_listing_event: Listings::RecordListingEvent.new)
      @record_listing_event = record_listing_event
    end

    def call(cart:, listing:, quantity:, now:)
      item = cart.items.find_or_initialize_by(listing: listing)
      item.update!(quantity: Domain::Cart::CartQuantity.within_stock(
        requested: item.quantity.to_i + quantity, available: listing.quantity
      ))

      @record_listing_event.call(
        listing: listing,
        customer_id: cart.customer_id,
        event_type: Domain::Listings::ListingEventType::CART_ADD,
        now: now
      )

      item
    end
  end
end
