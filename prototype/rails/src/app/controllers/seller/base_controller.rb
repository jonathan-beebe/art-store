# Compact form on purpose: `Seller` is the model class, so the portal
# namespace nests inside it and cannot be reopened with `module`.
class Seller::BaseController < ApplicationController
  include SellerAuthentication

  layout "seller"

  before_action :require_seller!

  helper_method :unread_notification_count

  private

  def logged_actor
    current_seller
  end

  def unread_notification_count
    @unread_notification_count ||= current_seller.notifications.unread.count
  end

  # Which side of a conversation the portal's visitor sits on.
  def current_participant
    current_seller
  end
end
