class Admin::OrdersController < Admin::BaseController
  def index
    @status = filter_from(:status, Order.statuses.keys)
    @customer_id = id_filter(:customer, :cus)
    @orders = Order.with_status(@status).for_customer(@customer_id)
      .includes(:customer, :items, :fulfillments).order(placed_at: :desc, id: :desc)
    @customers = Customer.order(:created_at, :id)
  end

  def show
    @order = Order.includes(:customer, items: :listing).find(params[:id])
    @payments = @order.payments.order(processed_at: :desc, id: :desc)
    @fulfillments = @order.fulfillments.includes(:seller, :order).order(created_at: :asc, id: :asc)
    @refunds = @order.refunds.order(created_at: :desc, id: :desc)
  end
end
