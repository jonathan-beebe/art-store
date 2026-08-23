class Seller::NotificationReadsController < Seller::BaseController
  def create
    notification = current_seller.notifications.find(params[:notification_id])
    notification.read!

    redirect_to seller_notifications_path, notice: "Marked as read."
  end
end
