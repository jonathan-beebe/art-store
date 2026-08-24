module Auth
  class AdminSessionsController < BaseController
    layout "admin"

    def new
      redirect_to admin_root_path if admin_signed_in?
    end

    def create
      link = send_magic_link(email: params[:email], actor_type: :admin)

      unless link.persisted?
        flash.now[:alert] = "Enter an email address to sign in."
        return render :new, status: :unprocessable_content
      end

      redirect_to admin_login_path, notice: "Sign-in link sent to #{link.email}."
    end

    def destroy
      sign_out_admin

      redirect_to admin_login_path, notice: "Signed out."
    end
  end
end
