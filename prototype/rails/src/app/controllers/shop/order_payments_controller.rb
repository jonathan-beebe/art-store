module Shop
  class OrderPaymentsController < BaseController
    rate_limit_guard :payment_attempt, by: -> { params[:id] }, only: :create

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

    # A tripped `payment_attempt` comes back on the same card form a decline
    # does, the sentence standing in for a decline reason.
    def render_too_many_requests(trip)
      @order = order_of_customer(params[:id])
      @payment = @order.payments.order(:created_at, :id).last
      flash.now[:alert] = rate_limit_message(trip)

      render :show, status: :too_many_requests
    end
  end
end
