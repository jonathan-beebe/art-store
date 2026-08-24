class Seller::MessagesController < Seller::BaseController
  include MessagingSite
  include SellerThreadPage

  private

  # A refused reply comes back on the portal's own thread page.
  def thread_template
    SellerThreadPage::TEMPLATE
  end
end
