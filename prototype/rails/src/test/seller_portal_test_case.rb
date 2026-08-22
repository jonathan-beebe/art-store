require "test_helper"
require "identity_test_case"

# The rows and the sign-in every seller portal test starts from. Order state is
# built through the commerce actions, so the portal reads back what the domain
# wrote rather than a hand-made row.
class SellerPortalTestCase < ActionDispatch::IntegrationTest
  include IdentityRecords

  APPROVED_CARD = "4242 4242 4242 4242"

  def sign_in_as(seller)
    post seller_send_magic_link_path, params: { email: seller.email }
    get flash[:debug_magic_link]
  end

  def signed_in_seller(**attributes)
    create_seller(email: unique_email("seller"), **attributes).tap { |seller| sign_in_as(seller) }
  end

  def other_seller
    create_seller(email: unique_email("rival"), shop_name: "Rival Studio")
  end

  def create_listing(seller, **attributes)
    Listing.create!({
      seller: seller,
      title: "Harbour at Dusk",
      slug: unique_slug,
      price_cents: 45_000,
      quantity: 1,
      status: Domain::Listings::ListingStatus::FOR_SALE
    }.merge(attributes))
  end

  def create_paid_order(listing)
    buyer = create_verified_customer(email: unique_email("buyer"))
    cart = Cart.create!(customer: buyer)
    Carts::AddToCart.new.call(cart: cart, listing: listing, quantity: 1, now: moment("2026-08-20 08:00:00"))

    order = Orders::PlaceOrder.new.call(
      cart: cart, purchaser: purchaser(buyer), shipping: shipping_address, now: moment("2026-08-20 09:00:00")
    )

    Orders::FinalizeOrder.new.call(order: order, card_number: APPROVED_CARD, now: moment("2026-08-20 10:00:00"))
  end

  def create_fulfillment(seller, listing: create_listing(seller))
    create_paid_order(listing).fulfillments.find_by!(seller: seller)
  end

  def create_delivered_fulfillment(seller, listing: create_listing(seller))
    fulfillment = create_fulfillment(seller, listing: listing)
    Fulfillments::MarkShipped.new.call(
      fulfillment: fulfillment, carrier: "Royal Mail", tracking_number: "RM123", now: moment("2026-08-21 09:00:00")
    )
    Fulfillments::ConfirmDelivered.new.call(fulfillment: fulfillment, now: moment("2026-08-22 09:00:00"))
  end

  def create_notification(seller, subject: "Item sold", **attributes)
    Notification.create!({ seller: seller, subject: subject, body: "Order #1 is paid." }.merge(attributes))
  end

  def create_listing_event(listing, event_type, occurred_at)
    Listings::RecordListingEvent.new.call(
      listing: listing, customer_id: nil, event_type: event_type, now: occurred_at
    )
  end

  def purchaser(customer)
    Domain::Orders::Purchaser.new(
      id: customer.id, email: customer.email, email_verified_at: customer.email_verified_at
    )
  end

  def shipping_address
    Domain::Orders::ShippingAddress.new(
      name: "Ada Lovelace", line1: "12 Analytical Way", line2: nil,
      city: "London", region: "Greater London", postal_code: "EC1A 1BB", country: "GB"
    )
  end

  def moment(text)
    Time.zone.parse(text)
  end

  def unique_email(role)
    "#{role}-#{SecureRandom.hex(4)}@example.test"
  end

  def unique_slug
    "listing-#{SecureRandom.hex(4)}"
  end
end
