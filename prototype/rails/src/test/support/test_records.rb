# The rows tests build for themselves. There are no fixture files: `fixtures
# :all` loads one shared directory for every suite in the app, so each test
# creates the rows it asks about.
module TestRecords
  APPROVED_CARD = "4242 4242 4242 4242"
  DECLINED_CARD = "4000 0000 0000 0002"
  UNFUNDED_CARD = "4000 0000 0000 9995"

  def create_seller(email: unique_email("seller"), shop_name: "Blue Kiln Studio", **attributes)
    Seller.create!(email: email, shop_name: shop_name, **attributes)
  end

  def create_verified_customer(email: unique_email("customer"), email_verified_at: Time.current, **attributes)
    Customer.create!(email: email, email_verified_at: email_verified_at, **attributes)
  end

  def create_anonymous_customer
    Customer.create!
  end

  def create_listing(seller = create_seller, **attributes)
    Listing.create!({
      seller: seller,
      title: "Harbour at Dusk",
      slug: unique_slug,
      description: "An oil study of the harbour after sundown.",
      medium: "Oil on canvas",
      dimensions: "40 x 60 cm",
      price_cents: 45_000,
      quantity: 1,
      status: Domain::Listings::ListingStatus::FOR_SALE
    }.merge(attributes))
  end

  # Returns the plaintext token beside the row, since only the digest is stored.
  def create_magic_link(
    email: "artist@example.com", actor_type: :seller, expires_at: 15.minutes.from_now, **attributes
  )
    token = SecureRandom.hex(32)
    link = MagicLink.create!(
      token_digest: MagicLink.digest(token),
      email: email,
      actor_type: actor_type,
      expires_at: expires_at,
      **attributes
    )

    [token, link]
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

  def balance_of(seller)
    Domain::Escrow::LedgerBalance.from(LedgerEntry.where(seller: seller).map(&:to_movement))
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
