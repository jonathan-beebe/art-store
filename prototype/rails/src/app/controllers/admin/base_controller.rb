# Compact form on purpose: `Admin` is the model class, so the admin namespace
# nests inside it and cannot be reopened with `module`.
class Admin::BaseController < ApplicationController
  include AdminAuthentication

  layout "admin"

  before_action :require_admin!

  helper_method :unread_message_count

  private

  def unread_message_count
    @unread_message_count ||= current_admin.unread_message_count
  end

  # Which side of a conversation the admin site's visitor sits on.
  def current_participant
    current_admin
  end
end
