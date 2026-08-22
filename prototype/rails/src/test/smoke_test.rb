require "test_helper"

# One walk of the whole product over HTTP, from an empty database to the
# seller's payout. Every step drives a real route and asserts both the rows it
# wrote and the page the person is looking at.
#
# Two browsers share the walk: the seller holds a portal session, and the
# customer arrives with no cookie at all, which is what gives the storefront an
# anonymous identity to merge on verification.
class SmokeTest < ActionDispatch::IntegrationTest
  SELLER_EMAIL = "smoke-seller@example.test".freeze
  CUSTOMER_EMAIL = "smoke-buyer@example.test".freeze
  LISTING_TITLE = "Meadow at Low Tide".freeze
  PRICE = "480.00".freeze
  PRICE_LABEL = "$480.00".freeze
  # The platform keeps 10%.
  NET_LABEL = "$432.00".freeze
  NET_CENTS = 43_200
  APPROVED_CARD = "4242 4242 4242 4242".freeze
  CARRIER = "Royal Mail".freeze
  TRACKING_NUMBER = "RM123456789GB".freeze

  # A Wednesday, so the week the delivery lands in is the week the payout run
  # settles whatever day the suite runs on.
  FROZEN_NOW = "2026-08-19 09:00:00".freeze
  NEXT_MONDAY = "2026-08-24 09:00:00".freeze

  test "a listing travels from the seller signing in to their weekly payout" do
    travel_to Time.zone.parse(FROZEN_NOW)

    @seller_browser = open_session
    @customer_browser = open_session

    seller = sign_in_seller
    listing = create_listing
    put_up_for_sale(listing)

    visitor = arrive_at_the_storefront(listing)
    view_listing(listing)
    favorite_listing(listing)
    add_to_cart(listing)

    order = place_guest_order
    verify_email(order)
    pay_with_an_approved_card(order)

    fulfillment = read_the_sale(order)
    mark_shipped(fulfillment)
    confirm_delivery(order, fulfillment)

    run_weekly_payout
    read_earnings(seller)

    assert_equal visitor.id, order.reload.customer_id
  end

  private

  def sign_in_seller
    @seller_browser.post seller_send_magic_link_path, params: { email: SELLER_EMAIL }
    @seller_browser.follow_redirect!
    @seller_browser.get magic_link_shown_to(@seller_browser)

    @seller_browser.assert_redirected_to seller_root_path
    @seller_browser.follow_redirect!
    @seller_browser.assert_select "header", text: /#{SELLER_EMAIL}/

    Seller.sole.tap do |seller|
      assert_equal SELLER_EMAIL, seller.email
      assert_not_nil seller.email_verified_at
    end
  end

  def create_listing
    @seller_browser.post seller_listings_path, params: {
      listing: {
        title: LISTING_TITLE,
        description: "Oil on linen, painted at the tideline.",
        medium: "Oil on linen",
        dimensions: "40 x 60 cm",
        price: PRICE,
        quantity: 1,
        image: uploaded_image
      }
    }

    @seller_browser.assert_redirected_to seller_listings_path
    @seller_browser.follow_redirect!
    @seller_browser.assert_select "[data-flash=notice]", text: /saved as a draft/

    Listing.sole.tap do |listing|
      assert_equal Domain::Listings::ListingStatus::DRAFT, listing.status
      assert_equal 48_000, listing.price_cents
      assert_predicate listing.image, :attached?
    end
  end

  def put_up_for_sale(listing)
    @seller_browser.post seller_listing_status_path(listing_id: listing.id),
      params: { status: Domain::Listings::ListingStatus::FOR_SALE }

    @seller_browser.assert_redirected_to seller_listings_path
    @seller_browser.follow_redirect!
    @seller_browser.assert_select "[data-listing=#{listing.id}] [data-cell=status]", text: "For sale"

    assert_equal Domain::Listings::ListingStatus::FOR_SALE, listing.reload.status
  end

  def arrive_at_the_storefront(listing)
    @customer_browser.get root_path

    @customer_browser.assert_response :success
    @customer_browser.assert_select "a[href=?]", shop_listing_path(listing.slug), text: /#{LISTING_TITLE}/

    Customer.sole.tap { |visitor| assert_predicate visitor, :anonymous? }
  end

  def view_listing(listing)
    @customer_browser.get shop_listing_path(listing.slug)

    @customer_browser.assert_response :success
    @customer_browser.assert_select "h1", text: LISTING_TITLE
    @customer_browser.assert_select "p", text: PRICE_LABEL

    assert_equal 1, listing.listing_events.where(event_type: Domain::Listings::ListingEventType::VIEW).count
  end

  def favorite_listing(listing)
    @customer_browser.post shop_toggle_favorite_path(slug: listing.slug)
    @customer_browser.get shop_favorites_path

    @customer_browser.assert_response :success
    @customer_browser.assert_select "a[href=?]", shop_listing_path(listing.slug), text: /#{LISTING_TITLE}/

    assert_equal 1, Favorite.where(listing: listing).count
  end

  def add_to_cart(listing)
    @customer_browser.post shop_add_to_cart_path(slug: listing.slug)

    @customer_browser.assert_redirected_to shop_cart_path
    @customer_browser.follow_redirect!
    @customer_browser.assert_select "[data-cart-subtotal]", text: PRICE_LABEL

    assert_equal 1, CartItem.sole.quantity
  end

  # A guest is never asked for a card here: the order is placed unpaid and
  # waits for the address behind it to be verified.
  def place_guest_order
    @customer_browser.get shop_checkout_path
    @customer_browser.assert_select "input[name=card_number]", count: 0

    @customer_browser.post shop_place_order_path, params: {
      email: CUSTOMER_EMAIL,
      shipping_name: "Casey Whitfield",
      shipping_line1: "18 Harbour Road",
      shipping_city: "Bristol",
      shipping_region: "Bristol",
      shipping_postal_code: "BS1 5TY",
      shipping_country: "GB"
    }

    Order.sole.tap do |order|
      @customer_browser.assert_redirected_to shop_order_path(order)
      assert_equal Domain::Orders::OrderStatus::PENDING_VERIFICATION, order.status
      assert_equal 48_000, order.total_cents
      assert_empty order.payments
    end
  end

  def verify_email(order)
    @customer_browser.follow_redirect!
    @customer_browser.assert_select "[data-verification-notice]", text: /#{CUSTOMER_EMAIL}/

    @customer_browser.get magic_link_shown_to(@customer_browser)

    @customer_browser.assert_redirected_to shop_order_payment_path(order)
    assert_not_nil Customer.sole.email_verified_at
  end

  def pay_with_an_approved_card(order)
    @customer_browser.follow_redirect!
    @customer_browser.assert_select "input[name=card_number]"
    assert_equal Domain::Orders::OrderStatus::AWAITING_PAYMENT, order.reload.status

    @customer_browser.post shop_pay_order_path(order), params: { card_number: APPROVED_CARD }

    @customer_browser.assert_redirected_to shop_order_path(order)
    @customer_browser.follow_redirect!
    @customer_browser.assert_select "[data-order-status]", text: "Paid"

    assert_equal Domain::Orders::OrderStatus::PAID, order.reload.status
    assert_equal "4242", order.payments.sole.card_last_four
    assert_equal NET_CENTS, ledger_amount_cents(Domain::Escrow::LedgerEntryType::HELD)
  end

  def read_the_sale(order)
    @seller_browser.get seller_notifications_path

    @seller_browser.assert_response :success
    @seller_browser.assert_select "[data-notification] p", text: /Item sold/
    @seller_browser.assert_select "[data-notification] p", text: /Order ##{order.id} is paid/

    @seller_browser.get seller_orders_path
    @seller_browser.assert_select "[data-group=awaiting_shipment] td", text: LISTING_TITLE

    Fulfillment.sole.tap do |fulfillment|
      assert_equal Domain::Orders::FulfillmentStatus::AWAITING_SHIPMENT, fulfillment.status
    end
  end

  def mark_shipped(fulfillment)
    @seller_browser.post seller_order_shipment_path(order_id: fulfillment.id),
      params: { carrier: CARRIER, tracking_number: TRACKING_NUMBER }

    @seller_browser.assert_redirected_to seller_order_path(fulfillment)
    @seller_browser.follow_redirect!
    @seller_browser.assert_select "[data-shipment]", text: /#{TRACKING_NUMBER}/

    assert_equal Domain::Orders::FulfillmentStatus::SHIPPED, fulfillment.reload.status
  end

  def confirm_delivery(order, fulfillment)
    @customer_browser.get shop_order_path(order)
    @customer_browser.assert_select "button", text: "Confirm delivery"

    @customer_browser.post shop_confirm_delivery_path(order_id: order.id, id: fulfillment.id)

    @customer_browser.assert_redirected_to shop_order_path(order)
    @customer_browser.follow_redirect!
    @customer_browser.assert_select "[data-order-status]", text: "Delivered"

    assert_equal Domain::Orders::FulfillmentStatus::DELIVERED, fulfillment.reload.status
    assert_equal Domain::Orders::OrderStatus::DELIVERED, order.reload.status
    assert_equal NET_CENTS, ledger_amount_cents(Domain::Escrow::LedgerEntryType::RELEASED)
  end

  def run_weekly_payout
    payouts = Escrow::RunWeeklyPayout.new.call(as_of: Time.zone.parse(NEXT_MONDAY))

    assert_equal 1, payouts.length
    assert_equal NET_CENTS, Payout.sole.amount_cents
  end

  def read_earnings(seller)
    @seller_browser.get seller_earnings_path

    @seller_browser.assert_response :success
    @seller_browser.assert_select "[data-stat=paid_out]", text: NET_LABEL
    @seller_browser.assert_select "[data-cell=net]", text: NET_LABEL
    @seller_browser.assert_select "[data-payout] [data-cell=amount]", text: NET_LABEL

    assert_equal NET_CENTS, seller.reload.escrow_balance.paid_out.cents
  end

  # The prototype has no mailbox, so the link is read back off the page that
  # printed it rather than out of the flash.
  def magic_link_shown_to(browser)
    alert = browser.css_select("[data-debug-alert] a").first

    assert_not_nil alert, "The debug alert rendered no magic link."
    alert["href"]
  end

  def ledger_amount_cents(entry_type)
    LedgerEntry.where(entry_type: entry_type).sole.amount_cents
  end

  def uploaded_image
    Rack::Test::UploadedFile.new(
      StringIO.new("\x89PNG\r\n\x1a\n"), "image/png", true, original_filename: "meadow.png"
    )
  end
end
