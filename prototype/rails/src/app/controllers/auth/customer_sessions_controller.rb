module Auth
  class CustomerSessionsController < BaseController
    layout "shop"

    def new
      return redirect_to shop_account_path if customer_signed_in?

      @redirect_to = url_from(params[:redirect_to])
    end

    def create
      @redirect_to = url_from(params[:redirect_to])
      link = send_magic_link(email: params[:email], actor_type: :customer, redirect_to: @redirect_to)
      return if link.nil?

      unless link.persisted?
        flash.now[:alert] = "Enter an email address to sign in."
        return render :new, status: :unprocessable_content
      end

      redirect_to customer_login_path(redirect_to: @redirect_to), notice: "Sign-in link sent to #{link.email}."
    end

    def destroy
      sign_out_customer

      redirect_to root_path, notice: "Signed out."
    end

    private

    # A tripped `magic_link_request` leaves the sign-in form on the page the
    # visitor was already looking at, the sentence standing in for the usual
    # "enter an address" alert.
    def render_too_many_requests(trip)
      flash.now[:alert] = rate_limit_message(trip)

      render :new, status: :too_many_requests
    end
  end
end
