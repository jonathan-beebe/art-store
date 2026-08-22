require "test_helper"

# Rows the identity tests need. There are no fixture files: `fixtures :all`
# loads one shared directory for every suite in the app, so identity keeps its
# rows local to the tests that ask for them.
module IdentityRecords
  def create_seller(email: "artist@example.com", **attributes)
    Seller.create!(email: email, **attributes)
  end

  def create_verified_customer(email: "buyer@example.com", **attributes)
    Customer.create!(email: email, email_verified_at: Time.current, **attributes)
  end

  def create_anonymous_customer
    Customer.create!
  end

  # Returns the plaintext token beside the row, since only the digest is stored.
  def create_magic_link(email: "artist@example.com", actor_type: Domain::Auth::ActorType::SELLER, **attributes)
    token = SecureRandom.hex(32)
    link = MagicLink.create!(
      token_digest: Domain::Auth::MagicLinkToken.digest(token),
      email: email,
      actor_type: actor_type,
      expires_at: 15.minutes.from_now,
      **attributes
    )

    [token, link]
  end
end

class IdentityTestCase < ActiveSupport::TestCase
  include IdentityRecords
end

class IdentityIntegrationTest < ActionDispatch::IntegrationTest
  include IdentityRecords

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

  def follow_magic_link
    get flash[:debug_magic_link]
  end
end
