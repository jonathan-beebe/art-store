# Compact form on purpose: `Seller` is the model class, so the portal
# namespace nests inside it and cannot be reopened with `module`.
class Seller::BaseController < ApplicationController
  include SellerAuthentication

  layout "seller"

  before_action :require_seller!

  helper_method :unread_notification_count, :own_items

  private

  def unread_notification_count
    @unread_notification_count ||= current_seller.notifications.unread.count
  end

  # An order may span sellers. These are the lines of it this seller ships.
  def own_items(order)
    order.items.select { |item| item.seller_id == current_seller.id }
  end
end
