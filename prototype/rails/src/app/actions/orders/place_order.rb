module Orders
  class PlaceOrder
    def call(cart:, purchaser:, shipping:, now:)
      raise ArgumentError, "an order needs at least one item" if cart.empty?

      items = cart.items.includes(:listing).to_a

      cart.transaction do
        order = open_order(purchaser, shipping, cart.subtotal, now)
        snapshot_items(order, items)
        split_by_seller(order, cart.subtotals_by_seller)
        take_stock(items)
        cart.items.destroy_all

        order
      end
    end

    private

    def open_order(purchaser, shipping, subtotal, now)
      Order.create!(
        shipping.to_h.transform_keys { |part| :"shipping_#{part}" }.merge(
          customer_id: purchaser.id,
          email: purchaser.email,
          status: Domain::Orders::OrderStatus.for_placement(purchaser),
          subtotal_cents: subtotal.cents,
          total_cents: subtotal.cents,
          placed_at: now
        )
      )
    end

    def snapshot_items(order, items)
      items.each do |item|
        order.items.create!(
          listing: item.listing,
          seller_id: item.listing.seller_id,
          title: item.listing.title,
          unit_price_cents: item.listing.price_cents,
          quantity: item.quantity
        )
      end
    end

    def split_by_seller(order, subtotals_by_seller)
      subtotals_by_seller.each do |seller_id, subtotal|
        order.fulfillments.create!(
          seller_id: seller_id,
          subtotal_cents: subtotal.cents,
          fee_cents: Domain::Escrow::Fee.platform(subtotal).cents,
          net_cents: Domain::Escrow::Fee.net(subtotal).cents
        )
      end
    end

    def take_stock(items)
      items.each do |item|
        listing = item.listing
        stock = Domain::Listings::ListingStock.after_sale(
          quantity: listing.quantity, status: listing.status, sold: item.quantity
        )
        listing.update!(quantity: stock.quantity, status: stock.status)
      end
    end
  end
end
