module Shop
  class AccountController < BaseController
    before_action :require_customer!

    def show
      @notifications = current_customer.notifications.order(id: :desc)
    end
  end
end
