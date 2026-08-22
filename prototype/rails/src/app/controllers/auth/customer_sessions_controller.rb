module Auth
  class CustomerSessionsController < BaseController
    layout "shop"

    def new
      return redirect_to shop_account_path if customer_signed_in?

      @redirect_to = local_redirect(params[:redirect_to])
    end

    def create
      email = params[:email]
      @redirect_to = local_redirect(params[:redirect_to])

      unless Domain::Auth::EmailAddress.valid?(email)
        flash.now[:alert] = "Enter an email address to sign in."
        return render :new, status: :unprocessable_content
      end

      send_magic_link(email: email, actor_type: Domain::Auth::ActorType::CUSTOMER, redirect_to: @redirect_to)

      redirect_to customer_login_path(redirect_to: @redirect_to),
        notice: "Sign-in link sent to #{Domain::Auth::EmailAddress.normalize(email)}."
    end

    def destroy
      sign_out_customer

      redirect_to root_path, notice: "Signed out."
    end
  end
end
