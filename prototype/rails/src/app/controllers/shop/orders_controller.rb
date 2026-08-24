module Shop
  class OrdersController < BaseController
    def index
      @orders = current_customer.orders.includes(:items).order(created_at: :desc, id: :desc)
    end

    def show
      @order = order_of_customer(params[:id])
      @fulfillments = @order.fulfillments.includes(:seller, :refunds).order(:created_at, :id)
      @items_by_seller = @order.items.group_by(&:seller_id)
      @payment = @order.payments.order(:created_at, :id).last
      @unpaid = @order.unpaid?
      @payable = @order.payable_by?(customer_signed_in?)
    end
  end
end
