class Seller::DashboardController < Seller::BaseController
  RECENT_NOTIFICATIONS = 5

  def show
    @tally = Domain::Reports::ListingStatusTally.from(current_seller.listings.group(:status).count)
    @awaiting_shipment = current_seller.fulfillments.awaiting_shipment.count
    @balance = current_seller.escrow_balance
    @unread_notifications = unread_notification_count
    @recent_notifications = current_seller.notifications.order(id: :desc).limit(RECENT_NOTIFICATIONS)
  end
end
