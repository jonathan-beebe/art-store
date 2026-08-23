module Auth
  class SellerSessionsController < BaseController
    layout "seller"

    def new
      redirect_to seller_root_path if seller_signed_in?
    end

    def create
      link = send_magic_link(email: params[:email], actor_type: :seller)

      unless link.persisted?
        flash.now[:alert] = "Enter an email address to sign in."
        return render :new, status: :unprocessable_content
      end

      redirect_to seller_login_path, notice: "Sign-in link sent to #{link.email}."
    end

    def destroy
      sign_out_seller

      redirect_to seller_login_path, notice: "Signed out."
    end
  end
end
