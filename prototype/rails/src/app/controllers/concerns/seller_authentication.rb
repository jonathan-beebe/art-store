module SellerAuthentication
  extend ActiveSupport::Concern

  included do
    helper_method :current_seller, :seller_signed_in?
  end

  private

  def current_seller
    @current_seller ||= Seller.find_by(id: session[:seller_id])
  end

  def seller_signed_in?
    current_seller.present?
  end

  def require_seller!
    return if seller_signed_in?

    redirect_to seller_login_path, alert: "Sign in to reach the seller portal."
  end

  # Rotates the session id (session-fixation protection) without discarding
  # whatever the customer or admin session keys already hold, so all three
  # actors can be signed in on the one browser at once.
  def sign_in_seller(seller)
    request.session_options[:renew] = true
    session[:seller_id] = seller.id
    @current_seller = seller
    Current.acting_as(seller)

    seller
  end

  # Deleting only this key leaves a customer or admin signed in on the same
  # session.
  def sign_out_seller
    session.delete(:seller_id)
    @current_seller = nil
    Current.acting_as(nil)
  end
end
