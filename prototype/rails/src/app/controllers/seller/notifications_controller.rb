class Seller::NotificationsController < Seller::BaseController
  def index
    @notifications = current_seller.notifications.order(created_at: :desc, id: :desc)
  end
end
