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

  def sign_in_seller(seller)
    reset_session
    session[:seller_id] = seller.id
    @current_seller = seller
  end

  def sign_out_seller
    reset_session
    @current_seller = nil
  end
end
