# Order history for two sellers, built through the same calls the seller
# portal and storefront make: a paid order awaiting shipment, a shipped
# order, and a delivered order whose escrow is released and paid out.
module Seeds
  module OrderHistory
    module_function

    APPROVED_CARD = "4242424242424242"

    SHIPPING = {
      shipping_name: "Casey Whitfield", shipping_line1: "48 Harbor Street", shipping_line2: nil,
      shipping_city: "Portland", shipping_region: "Oregon", shipping_postal_code: "97201",
      shipping_country: "US"
    }.freeze

    def create_all
      customer = Customer.find_by!(email: Seeds::Customers::CASEY_EMAIL)

      place_and_pay(customer, "Ash-Glazed Tea Bowl", Time.utc(2026, 7, 6, 9, 0, 0))

      shipped = place_and_pay(customer, "Kitchen Table, Late Morning", Time.utc(2026, 7, 7, 9, 0, 0))
      ship(shipped, "UPS", "1Z999AA10123456784", Time.utc(2026, 7, 8, 9, 0, 0))

      delivered = place_and_pay(customer, "Standing Figure in Reclaimed Oak", Time.utc(2026, 7, 6, 11, 0, 0))
      ship(delivered, "USPS", "9400111899223197428490", Time.utc(2026, 7, 8, 10, 0, 0))
      deliver(delivered, Time.utc(2026, 7, 10, 14, 0, 0))

      Escrow::RunWeeklyPayout.new.call(as_of: Time.utc(2026, 7, 16, 9, 0, 0))
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
      Fulfillments::MarkShipped.new.call(
        fulfillment: order.fulfillments.sole, carrier: carrier, tracking_number: tracking_number, now: shipped_at
      )
    end

    def deliver(order, delivered_at)
      Fulfillments::ConfirmDelivered.new.call(fulfillment: order.fulfillments.sole, now: delivered_at)
    end
  end
end
