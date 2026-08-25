# Order history for two sellers, built through the same calls the seller
# portal and storefront make: a paid order awaiting shipment, a shipped
# order, and a delivered order whose escrow is released and paid out.
module Seeds
  module OrderHistory
    module_function

    APPROVED_CARD = "4242424242424242"

    SHIPPING = {
      shipping_name: "Hermione Granger", shipping_line1: "12 Heathgate", shipping_line2: nil,
      shipping_city: "London", shipping_region: "Hampstead", shipping_postal_code: "NW11 7EB",
      shipping_country: "GB"
    }.freeze

    def create_all
      customer = Customer.find_by!(email: Seeds::Customers::HERMIONE_EMAIL)

      place_and_pay(customer, "Burrow Kitchen Tea Bowl", Time.utc(2026, 7, 6, 9, 0, 0))

      shipped = place_and_pay(customer, "Gryffindor Common Room, Late Morning", Time.utc(2026, 7, 7, 9, 0, 0))
      ship(shipped, "Owl Post", "OWL-2263-1187-GB", Time.utc(2026, 7, 8, 9, 0, 0))

      delivered = place_and_pay(customer, "Garden Gnome in Reclaimed Oak", Time.utc(2026, 7, 6, 11, 0, 0))
      ship(delivered, "Knight Bus Parcel", "KB-9400-1189-2231", Time.utc(2026, 7, 8, 10, 0, 0))
      deliver(delivered, Time.utc(2026, 7, 10, 14, 0, 0))

      Payout.run_weekly(as_of: Time.utc(2026, 7, 16, 9, 0, 0))
    end

    def place_and_pay(customer, listing_title, placed_at)
      cart = Cart.create!(customer_id: customer.id)
      listing = Listing.find_by!(title: listing_title)

      cart.add(listing, at: placed_at)
      order = Order.place(
        cart: cart, customer: customer, email: customer.email, email_verified: true,
        shipping: SHIPPING, at: placed_at
      )

      order.pay!(APPROVED_CARD, at: placed_at + 5.minutes)
    end

    def ship(order, carrier, tracking_number, shipped_at)
      order.fulfillments.sole.ship!(carrier: carrier, tracking_number: tracking_number, at: shipped_at)
    end

    def deliver(order, delivered_at)
      order.fulfillments.sole.deliver!(at: delivered_at)
    end
  end
end
