module Shop
  class AccountController < BaseController
    before_action :require_customer!

    def show
    end
  end
end
