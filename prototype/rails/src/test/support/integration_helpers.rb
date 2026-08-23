# What the tests that drive the app over HTTP need on top of the record
# builders: the sign-in walk, the cookies the app sets, and the order state the
# seller portal reads back. Order state is built through the commerce actions,
# so the portal reads what the domain wrote rather than a hand-made row.
module IntegrationHelpers
  # Signed cookies are opaque to the Rack::Test jar, so the assertions read
  # them back through the same jar the app writes with.
  def signed_cookie(name)
    ActionDispatch::Cookies::CookieJar.build(request, cookies.to_hash).signed[name]
  end

  def sign_in_as_seller(email: "artist@example.com")
    post seller_send_magic_link_path, params: { email: email }
    follow_magic_link
  end

  def sign_in_as_customer(email: "buyer@example.com", redirect_to: nil)
    post customer_send_magic_link_path, params: { email: email, redirect_to: redirect_to }
    follow_magic_link
  end

  def sign_in_as(seller)
    sign_in_as_seller(email: seller.email)
  end

  def follow_magic_link
    get flash[:debug_magic_link]
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

  def signed_in_seller(**attributes)
    create_seller(**attributes).tap { |seller| sign_in_as(seller) }
  end

  def other_seller
    create_seller(email: unique_email("rival"), shop_name: "Rival Studio")
  end

  def create_paid_order(listing)
    buyer = create_verified_customer(email: unique_email("buyer"))
    cart = Cart.create!(customer: buyer)
    cart.add(listing, at: moment("2026-08-20 08:00:00"))

    order = Orders::PlaceOrder.new.call(
      cart: cart, purchaser: purchaser(buyer), shipping: shipping_address, now: moment("2026-08-20 09:00:00")
    )

    Orders::FinalizeOrder.new.call(
      order: order, card_number: TestRecords::APPROVED_CARD, now: moment("2026-08-20 10:00:00")
    )
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
    listing.record_event!(event_type, at: occurred_at)
  end
end
