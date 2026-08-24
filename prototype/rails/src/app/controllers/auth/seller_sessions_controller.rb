module Auth
  class SellerSessionsController < BaseController
    layout "seller"

    def new
      redirect_to seller_root_path if seller_signed_in?
    end

    def create
      link = send_magic_link(email: params[:email], actor_type: :seller)
      return if link.nil?

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
