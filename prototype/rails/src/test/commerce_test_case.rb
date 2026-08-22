require "test_helper"

# The rows every commerce test starts from, and the walk a customer takes from
# an empty cart to an order waiting on a card.
class CommerceTestCase < ActiveSupport::TestCase
  APPROVED_CARD = "4242 4242 4242 4242"
  DECLINED_CARD = "4000 0000 0000 0002"
  UNFUNDED_CARD = "4000 0000 0000 9995"

  def seller(shop_name = "Blue Kiln Studio")
    Seller.create!(email: unique_email("seller"), shop_name: shop_name)
  end

  def customer(email_verified_at: moment("2026-08-19 09:00:00"))
    Customer.create!(email: unique_email("customer"), email_verified_at: email_verified_at)
  end

  def anonymous_customer
    Customer.create!(email: nil, email_verified_at: nil)
  end

  def listing(seller, **attributes)
    Listing.create!({
      seller: seller,
      title: "Harbour at Dusk",
      slug: unique_slug,
      price_cents: 45_000,
      quantity: 1,
      status: Domain::Listings::ListingStatus::FOR_SALE
    }.merge(attributes))
  end

  def purchaser(customer)
    Domain::Orders::Purchaser.new(
      id: customer.id, email: customer.email, email_verified_at: customer.email_verified_at
    )
  end

  def cart_for(customer)
    Cart.create!(customer: customer)
  end

  def cart_holding(customer, *listings)
    cart_for(customer).tap do |cart|
      listings.each do |listing|
        Carts::AddToCart.new.call(cart: cart, listing: listing, quantity: 1, now: moment("2026-08-20 08:00:00"))
      end
    end
  end

  def order_for(customer, *listings)
    Orders::PlaceOrder.new.call(
      cart: cart_holding(customer, *listings),
      purchaser: purchaser(customer),
      shipping: shipping_address,
      now: moment("2026-08-20 09:00:00")
    )
  end

  def paid_order_for(customer, *listings)
    Orders::FinalizeOrder.new.call(
      order: order_for(customer, *listings), card_number: APPROVED_CARD, now: moment("2026-08-20 10:00:00")
    )
  end

  def shipping_address
    Domain::Orders::ShippingAddress.new(
      name: "Ada Lovelace", line1: "12 Analytical Way", line2: nil,
      city: "London", region: "Greater London", postal_code: "EC1A 1BB", country: "GB"
    )
  end

  def balance_of(seller)
    Domain::Escrow::LedgerBalance.from(LedgerEntry.where(seller: seller).map(&:to_movement))
  end

  def moment(text)
    Time.zone.parse(text)
  end

  private

  def unique_email(role)
    "#{role}-#{SecureRandom.hex(4)}@example.test"
  end

  def unique_slug
    "listing-#{SecureRandom.hex(4)}"
  end
end
