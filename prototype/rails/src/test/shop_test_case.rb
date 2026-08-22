require "identity_test_case"

# Rows and request helpers the storefront tests share. The storefront is walked
# over HTTP, so the identity travels in the same signed cookie the app sets.
module ShopRecords
  APPROVED_CARD = "4242 4242 4242 4242"
  DECLINED_CARD = "4000 0000 0000 0002"
  UNFUNDED_CARD = "4000 0000 0000 9995"

  def create_artist(shop_name: "Blue Kiln Studio")
    Seller.create!(email: "seller-#{SecureRandom.hex(4)}@example.test", shop_name: shop_name)
  end

  def create_listing(seller: create_artist, **attributes)
    Listing.create!({
      seller: seller,
      title: "Harbour at Dusk",
      slug: "listing-#{SecureRandom.hex(4)}",
      description: "An oil study of the harbour after sundown.",
      medium: "Oil on canvas",
      dimensions: "40 x 60 cm",
      price_cents: 45_000,
      quantity: 1,
      status: Domain::Listings::ListingStatus::FOR_SALE
    }.merge(attributes))
  end

  def shipping_params
    {
      shipping_name: "Ada Lovelace",
      shipping_line1: "12 Analytical Way",
      shipping_line2: "",
      shipping_city: "London",
      shipping_region: "Greater London",
      shipping_postal_code: "EC1A 1BB",
      shipping_country: "GB"
    }
  end

  # The identity cookie outlives a session, so dropping the session alone
  # leaves a returning visitor recognised but signed out.
  def end_session
    cookies.delete(Rails.application.config.session_options.fetch(:key))
  end

  # The customer the identity cookie points at after the last request.
  def visiting_customer
    Customer.find(signed_cookie(CustomerIdentity::COOKIE))
  end

  def order_of_visiting_customer
    visiting_customer.orders.sole
  end
end

class ShopIntegrationTest < IdentityIntegrationTest
  include ShopRecords
end
