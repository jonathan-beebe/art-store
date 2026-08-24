module Shop
  class MessagesController < BaseController
    include MessagingSite

    private

    # A refused reply comes back on the storefront's own thread page.
    def thread_template
      "shop/conversations/show"
    end
  end
end
