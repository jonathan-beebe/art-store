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

  def create_admin(email: unique_email("admin"), name: "Ops", **attributes)
    Admin.create!(email: email, name: name, **attributes)
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
      status: :for_sale
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

    [ token, link ]
  end

  def shipping_address(**overrides)
    {
      shipping_name: "Ada Lovelace", shipping_line1: "12 Analytical Way", shipping_line2: nil,
      shipping_city: "London", shipping_region: "Greater London", shipping_postal_code: "EC1A 1BB",
      shipping_country: "GB"
    }.merge(overrides)
  end

  def cart_for(customer)
    Cart.create!(customer: customer)
  end

  def cart_holding(customer, *listings)
    cart_for(customer).tap do |cart|
      listings.each { |listing| cart.add(listing, at: moment("2026-08-20 08:00:00")) }
    end
  end

  def order_for(customer, *listings)
    Order.place(
      cart: cart_holding(customer, *listings),
      customer: customer,
      email: customer.email || "guest@example.test",
      email_verified: customer.email_verified_at.present?,
      shipping: shipping_address,
      at: moment("2026-08-20 09:00:00")
    )
  end

  def paid_order_for(customer, *listings)
    order_for(customer, *listings).pay!(APPROVED_CARD, at: moment("2026-08-20 10:00:00"))
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
