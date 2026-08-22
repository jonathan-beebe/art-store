module Auth
  class SellerSessionsController < BaseController
    layout "seller"

    def new
      redirect_to seller_root_path if seller_signed_in?
    end

    def create
      email = params[:email]

      unless Domain::Auth::EmailAddress.valid?(email)
        flash.now[:alert] = "Enter an email address to sign in."
        return render :new, status: :unprocessable_content
      end

      send_magic_link(email: email, actor_type: Domain::Auth::ActorType::SELLER)

      redirect_to seller_login_path, notice: "Sign-in link sent to #{Domain::Auth::EmailAddress.normalize(email)}."
    end

    def destroy
      sign_out_seller

      redirect_to seller_login_path, notice: "Signed out."
    end
  end
end
