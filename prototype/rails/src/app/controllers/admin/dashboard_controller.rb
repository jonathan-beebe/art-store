class Admin::DashboardController < Admin::BaseController
  def show
    @sellers = Seller.order(:id)
    @customers = Customer.verified.order(:id)
  end
end
