module Shop
  class OrdersController < BaseController
    def index
      @orders = current_customer.orders.includes(:items).order(id: :desc)
    end

    def show
      @order = order_of_customer(params[:id])
      @fulfillments = @order.fulfillments.includes(:seller).order(:id)
      @items_by_seller = @order.items.group_by(&:seller_id)
      @payment = @order.payments.order(:id).last
      @unpaid = Domain::Orders::OrderPayment.unpaid?(@order.status)
      @payable = Domain::Orders::OrderPayment.payable?(@order.status, customer_signed_in?)
    end
  end
end
