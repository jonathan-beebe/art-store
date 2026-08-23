module Shop
  class NotificationsController < BaseController
    before_action :require_customer!

    def update
      current_customer.notifications.find(params[:id]).read!(at: now)

      redirect_to shop_account_path
    end
  end
end
