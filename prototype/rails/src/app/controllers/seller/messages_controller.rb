class Seller::MessagesController < Seller::BaseController
  include MessagingSite

  private

  # A refused reply comes back on the portal's own thread page.
  def thread_template
    "seller/conversations/show"
  end
end
