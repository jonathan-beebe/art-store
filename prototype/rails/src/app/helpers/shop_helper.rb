module ShopHelper
  # The header carries these on every storefront page, including the sign-in
  # page, which runs under the shop layout without a Shop::BaseController.
  def current_cart
    @current_cart ||= Carts::CurrentCart.new.call(customer: current_customer)
  end

  def cart_item_count
    current_cart.items.sum(:quantity)
  end

  def unread_notification_count
    current_customer.notifications.unread.count
  end

  def shop_name_of(seller)
    Domain::Shop::ShopName.of(shop_name: seller.shop_name, email: seller.email)
  end

  def money(cents)
    Domain::Money.from_cents(cents).format
  end
end
