module Shop
  class NotificationReadsController < BaseController
    before_action :require_customer!

    def create
      current_customer.notifications.find(params[:id]).read!

      redirect_to shop_account_path
    end
  end
end
