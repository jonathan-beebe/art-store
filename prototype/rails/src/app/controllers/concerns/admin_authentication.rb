module AdminAuthentication
  extend ActiveSupport::Concern

  included do
    helper_method :current_admin, :admin_signed_in?
  end

  private

  def current_admin
    @current_admin ||= Admin.find_by(id: session[:admin_id])
  end

  def admin_signed_in?
    current_admin.present?
  end

  def require_admin!
    return if admin_signed_in?

    redirect_to admin_login_path, alert: "Sign in to reach the admin site."
  end

  def sign_in_admin(admin)
    reset_session
    session[:admin_id] = admin.id
    @current_admin = admin
    Current.acting_as(admin)

    admin
  end

  def sign_out_admin
    reset_session
    @current_admin = nil
    Current.acting_as(nil)
  end
end
