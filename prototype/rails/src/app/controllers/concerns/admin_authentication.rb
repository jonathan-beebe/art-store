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

  # Rotates the session id (session-fixation protection) without discarding
  # whatever the customer or seller session keys already hold, so all three
  # actors can be signed in on the one browser at once.
  def sign_in_admin(admin)
    request.session_options[:renew] = true
    session[:admin_id] = admin.id
    @current_admin = admin
    Current.acting_as(admin)

    admin
  end

  # Deleting only this key leaves a customer or seller signed in on the same
  # session.
  def sign_out_admin
    session.delete(:admin_id)
    @current_admin = nil
    Current.acting_as(nil)
  end
end
