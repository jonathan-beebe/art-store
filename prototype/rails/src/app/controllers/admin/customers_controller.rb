class Admin::CustomersController < Admin::BaseController
  # The admin site knows the customers who have given an address. A row with
  # none is a browser the storefront is holding a cart for, not an account.
  def show
    @customer = Customer.verified.find(params[:id])
    @order_count = @customer.orders.count
  end
end
