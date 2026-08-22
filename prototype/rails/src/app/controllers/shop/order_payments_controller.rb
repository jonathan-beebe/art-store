module Shop
  class OrderPaymentsController < BaseController
    def show
      order = order_of_customer(params[:id])
      return redirect_to shop_order_path(order) unless Domain::Orders::OrderPayment.unpaid?(order.status)
      return redirect_to sign_in_to_pay(order) unless customer_signed_in?

      @order = Orders::MarkAwaitingPayment.new.call(order: order)
      @payment = @order.payments.order(:id).last
    end

    def create
      order = payable_order

      Orders::FinalizeOrder.new.call(order: order, card_number: params[:card_number], now: now)

      redirect_to shop_order_path(order)
    end

    private

    # Verifying the address is what carries a guest's order from
    # pending_verification to a status a card can settle, so the card form is
    # unreachable until the visitor holds a session.
    def payable_order
      order = order_of_customer(params[:id])
      raise ActiveRecord::RecordNotFound unless customer_signed_in?

      Orders::MarkAwaitingPayment.new.call(order: order)
      raise ActiveRecord::RecordNotFound unless Domain::Orders::OrderPayment.awaits_card?(order.status)

      order
    end

    def sign_in_to_pay(order)
      customer_login_path(redirect_to: shop_order_payment_path(order))
    end
  end
end
