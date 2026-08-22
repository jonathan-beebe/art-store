# Order history for two sellers, built through the same actions the seller
# portal and storefront call: a paid order awaiting shipment, a shipped
# order, and a delivered order whose escrow is released and paid out.
module Seeds
  module OrderHistory
    module_function

    APPROVED_CARD = "4242424242424242"

    def create_all
      customer = Customer.find_by!(email: Seeds::Customers::CASEY_EMAIL)
      purchaser = Domain::Orders::Purchaser.new(
        id: customer.id, email: customer.email, email_verified_at: customer.email_verified_at
      )

      place_and_pay(customer, purchaser, "Ash-Glazed Tea Bowl", Time.utc(2026, 7, 6, 9, 0, 0))

      shipped = place_and_pay(customer, purchaser, "Kitchen Table, Late Morning", Time.utc(2026, 7, 7, 9, 0, 0))
      ship(shipped, "UPS", "1Z999AA10123456784", Time.utc(2026, 7, 8, 9, 0, 0))

      delivered = place_and_pay(customer, purchaser, "Standing Figure in Reclaimed Oak", Time.utc(2026, 7, 6, 11, 0, 0))
      ship(delivered, "USPS", "9400111899223197428490", Time.utc(2026, 7, 8, 10, 0, 0))
      deliver(delivered, Time.utc(2026, 7, 10, 14, 0, 0))

      Escrow::RunWeeklyPayout.new.call(as_of: Time.utc(2026, 7, 16, 9, 0, 0))
    end

    def place_and_pay(customer, purchaser, listing_title, placed_at)
      cart = Cart.create!(customer_id: customer.id)
      listing = Listing.find_by!(title: listing_title)

      Carts::AddToCart.new.call(cart: cart, listing: listing, quantity: 1, now: placed_at)
      order = Orders::PlaceOrder.new.call(cart: cart, purchaser: purchaser, shipping: shipping_address, now: placed_at)

      Orders::FinalizeOrder.new.call(order: order, card_number: APPROVED_CARD, now: placed_at + 5.minutes)
    end

    def ship(order, carrier, tracking_number, shipped_at)
      Fulfillments::MarkShipped.new.call(
        fulfillment: order.fulfillments.sole, carrier: carrier, tracking_number: tracking_number, now: shipped_at
      )
    end

    def deliver(order, delivered_at)
      Fulfillments::ConfirmDelivered.new.call(fulfillment: order.fulfillments.sole, now: delivered_at)
    end

    def shipping_address
      Domain::Orders::ShippingAddress.new(
        name: "Casey Whitfield", line1: "48 Harbor Street", line2: nil,
        city: "Portland", region: "Oregon", postal_code: "97201", country: "US"
      )
    end
  end
end
