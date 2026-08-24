class Admin::MessagesController < Admin::BaseController
  include MessagingSite

  private

  # A refused reply comes back on the admin site's own thread page.
  def thread_template
    "admin/conversations/show"
  end
end
