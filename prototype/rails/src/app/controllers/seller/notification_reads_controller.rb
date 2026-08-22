class Seller::NotificationReadsController < Seller::BaseController
  def create
    notification = current_seller.notifications.find(params[:notification_id])
    notification.update!(read_at: Time.current)

    redirect_to seller_notifications_path, notice: "Marked as read."
  end
end
