class Admin::DashboardController < Admin::BaseController
  def show
    @sellers = Seller.order(:created_at, :id)
    @customers = Customer.verified.order(:created_at, :id)
  end
end
