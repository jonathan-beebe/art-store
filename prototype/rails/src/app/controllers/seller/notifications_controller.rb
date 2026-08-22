class Seller::NotificationsController < Seller::BaseController
  def index
    @notifications = current_seller.notifications.order(id: :desc)
  end
end
