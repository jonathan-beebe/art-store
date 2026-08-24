module Shop
  class CheckoutsController < BaseController
    include MagicLinkSender

    rate_limit_guard :checkout, by: -> { current_customer.id }, only: :create
    before_action -> { refuse_blocked_customer(to: shop_cart_path) }, only: :create

    INCOMPLETE = "Enter an email address and a full shipping address.".freeze
    UNAVAILABLE = "Your cart changed before checkout. Take these out before placing the order.".freeze

    def show
      return redirect_to shop_cart_path if current_cart.empty?

      @order = Order.new(email: verified_account&.email.to_s)
      load_summary
    end

    # The card is only asked for once the address behind the order is verified,
    # which is why a guest leaves here with a link instead of a receipt.
    def create
      return redirect_to shop_cart_path if current_cart.empty?

      @order = Order.place(
        cart: current_cart, customer: current_customer, email: buyer_email,
        email_verified: verified_account.present?, shipping: shipping_params, at: Time.current
      )

      return reject_unavailable if @order.blocked_lines.present?
      return reject_incomplete unless @order.persisted?
      return charge(@order) if @order.awaiting_payment?

      send_verification_link(@order)
    end

    private

    def charge(order)
      order.pay!(params[:card_number])

      redirect_to shop_order_path(order)
    end

    def send_verification_link(order)
      link = send_magic_link(
        email: order.email, actor_type: :customer, redirect_to: shop_order_payment_path(order)
      )
      return if link.nil?

      redirect_to shop_order_path(order)
    end

    def reject_incomplete
      load_summary
      flash.now[:alert] = INCOMPLETE

      render :show, status: :unprocessable_content
    end

    def reject_unavailable
      load_summary
      flash.now[:alert] = UNAVAILABLE

      render :show, status: :unprocessable_content
    end

    # A signed-in customer buys under the address on their account, so a
    # submitted field cannot move an order onto someone else's identity. A
    # guest buys under the address they typed and verifies it afterwards.
    def buyer_email
      verified_account&.email || params[:email]
    end

    def shipping_params
      params.permit(*Order::SHIPPING_FIELDS).to_h.symbolize_keys
    end

    def load_summary
      @items = current_cart.items.includes(:listing).order(:created_at, :id)
      @subtotal = current_cart.subtotal
    end

    # A tripped `checkout` or `magic_link_request` (the guest verification
    # link this action sends) both leave the visitor on the checkout form
    # they were already filling in.
    def render_too_many_requests(trip)
      @order = Order.new(email: verified_account&.email.to_s)
      load_summary
      flash.now[:alert] = rate_limit_message(trip)

      render :show, status: :too_many_requests
    end
  end
end
