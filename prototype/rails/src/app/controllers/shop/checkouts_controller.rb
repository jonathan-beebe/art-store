module Shop
  class CheckoutsController < BaseController
    include MagicLinkSender

    INCOMPLETE = "Enter an email address and a full shipping address.".freeze

    def show
      return redirect_to shop_cart_path if current_cart.empty?

      @form = blank_form
      load_summary
    end

    # The card is only asked for once the address behind the order is verified,
    # which is why a guest leaves here with a link instead of a receipt.
    def create
      return redirect_to shop_cart_path if current_cart.empty?

      @form = submitted_form
      return reject_incomplete unless @form.complete?

      purchaser = checkout_purchaser
      order = Order.place(
        cart: current_cart, customer: current_customer, email: purchaser.email,
        email_verified: purchaser.email_verified?, shipping: shipping_attributes, at: Time.current
      )

      return charge(order) if order.payable_by?(purchaser.email_verified?)

      send_verification_link(order, purchaser)
    end

    private

    def charge(order)
      order.pay!(params[:card_number])

      redirect_to shop_order_path(order)
    end

    def shipping_attributes
      @form.shipping.to_h.transform_keys { |part| :"shipping_#{part}" }
    end

    def send_verification_link(order, purchaser)
      send_magic_link(
        email: purchaser.email, actor_type: :customer, redirect_to: shop_order_payment_path(order)
      )

      redirect_to shop_order_path(order)
    end

    def reject_incomplete
      load_summary
      flash.now[:alert] = INCOMPLETE

      render :show, status: :unprocessable_content
    end

    def checkout_purchaser
      Domain::Shop::CheckoutPurchaser.for_checkout(
        id: current_customer.id,
        account_email: verified_account&.email,
        account_verified_at: verified_account&.email_verified_at,
        submitted_email: @form.email
      )
    end

    def blank_form
      Domain::Shop::CheckoutForm.from_input(email: verified_account&.email, shipping: {})
    end

    def submitted_form
      Domain::Shop::CheckoutForm.from_input(
        email: params[:email],
        shipping: Domain::Orders::ShippingAddress.members.to_h { |part| [part, params[:"shipping_#{part}"]] }
      )
    end

    def load_summary
      @items = current_cart.items.includes(:listing).order(:id)
      @subtotal = current_cart.subtotal
    end
  end
end
