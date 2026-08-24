module Shop
  class OrderPaymentsController < BaseController
    def show
      order = order_of_customer(params[:id])
      return redirect_to shop_order_path(order) unless order.unpaid?
      return redirect_to sign_in_to_pay(order) unless customer_signed_in?

      @order = order.mark_awaiting_payment!
      @payment = @order.payments.order(:created_at, :id).last
    end

    def create
      order = payable_order

      order.pay!(params[:card_number])

      return reject_unavailable(order) if order.blocked_lines.present?

      redirect_to shop_order_path(order)
    end

    private

    def reject_unavailable(order)
      @order = order
      @payment = @order.payments.order(:created_at, :id).last

      render :show, status: :unprocessable_content
    end

    # Verifying the address is what carries a guest's order from
    # pending_verification to a status a card can settle, so the card form is
    # unreachable until the visitor holds a session.
    def payable_order
      order = order_of_customer(params[:id])
      raise ActiveRecord::RecordNotFound unless customer_signed_in?

      order.mark_awaiting_payment!
      raise ActiveRecord::RecordNotFound unless order.awaits_card?

      order
    end

    def sign_in_to_pay(order)
      customer_login_path(redirect_to: shop_order_payment_path(order))
    end
  end
end
