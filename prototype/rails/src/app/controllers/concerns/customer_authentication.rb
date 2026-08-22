module CustomerAuthentication
  extend ActiveSupport::Concern
  include CustomerIdentity

  private

  # An identity is not a sign-in: a page behind this needs the verified
  # customer in the session, not just the cookie.
  def require_customer!
    return if customer_signed_in?

    redirect_to customer_login_path(redirect_to: request.fullpath), alert: "Sign in to reach your account."
  end
end
