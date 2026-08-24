module Shop
  # The desk a customer reaches from their account page. A visitor who has
  # given no address still has an identity, so the thread has somewhere to
  # hang and travels with them when they verify.
  class SupportsController < BaseController
    rate_limit_guard :conversation_open, by: -> { current_participant.id }, only: :create

    def create
      admin = Admin.on_duty
      return redirect_back_or_to(root_path, alert: "Nobody is on the support desk yet.") if admin.nil?

      conversation = Conversation.open(kind: :admin_customer, admin: admin, customer: current_customer)

      redirect_to shop_conversation_path(conversation)
    end

    private

    # A tripped `conversation_open` comes back on the account page the
    # "Contact support" button sits on, the sentence standing in for a field
    # error — there is no body to preserve, since the button carries none.
    def render_too_many_requests(trip)
      @notifications = current_customer.notifications.order(created_at: :desc, id: :desc)
      flash.now[:alert] = rate_limit_message(trip)

      render "shop/account/show", status: :too_many_requests
    end
  end
end
