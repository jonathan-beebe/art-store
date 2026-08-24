module CustomerIdentity
  extend ActiveSupport::Concern

  COOKIE = :customer_id
  # A browsing history is worth more than a session, so the cookie outlives one.
  COOKIE_LIFETIME = 1.year

  included do
    helper_method :current_customer, :customer_signed_in?, :current_cart
  end

  private

  # Every storefront request has a customer behind it, so favorites, carts, and
  # orders have somewhere to hang before anyone gives an address.
  def current_customer
    @current_customer ||= remember_customer(signed_in_customer || customer_from_cookie || Customer.create!)
  end

  def customer_signed_in?
    signed_in_customer.present?
  end

  # The cart behind the request, resolved once so the header and the page it
  # wraps read the same one.
  def current_cart
    @current_cart ||= current_customer.current_cart
  end

  def resolve_customer_identity
    current_customer
  end

  def signed_in_customer
    return @signed_in_customer if defined?(@signed_in_customer)

    @signed_in_customer = Customer.verified.find_by(id: session[:customer_id])
  end

  def customer_from_cookie
    Customer.from_cookie(cookies.signed[COOKIE])
  end

  # Rotates the session id (session-fixation protection) without discarding
  # whatever the seller or admin session keys already hold, so all three
  # actors can be signed in on the one browser at once.
  def sign_in_customer(customer)
    request.session_options[:renew] = true
    session[:customer_id] = customer.id
    @signed_in_customer = customer
    @current_customer = remember_customer(customer)
    Current.acting_as(customer)

    @current_customer
  end

  # Dropping the cookie hands the browser a clean anonymous identity on its
  # next storefront request rather than the account it just left. Deleting
  # only this key leaves a seller or admin signed in on the same session.
  def sign_out_customer
    session.delete(:customer_id)
    cookies.delete(COOKIE)
    @signed_in_customer = nil
    @current_customer = nil
    Current.acting_as(nil)
  end

  def remember_customer(customer)
    cookies.signed[COOKIE] = { value: customer.id, expires: COOKIE_LIFETIME.from_now, httponly: true }

    customer
  end
end
