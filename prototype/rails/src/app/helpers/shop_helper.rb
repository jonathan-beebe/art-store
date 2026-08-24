module ShopHelper
  # The header carries these on every storefront page, including the sign-in
  # page, which runs under the shop layout without a Shop::BaseController.
  def cart_item_count
    current_cart.item_count
  end

  def unread_notification_count
    current_customer.notifications.unread.count
  end
end
