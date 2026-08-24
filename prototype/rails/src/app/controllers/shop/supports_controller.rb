module Shop
  # The desk a customer reaches from their account page. A visitor who has
  # given no address still has an identity, so the thread has somewhere to
  # hang and travels with them when they verify.
  class SupportsController < BaseController
    def create
      admin = Admin.on_duty
      return redirect_back_or_to(root_path, alert: "Nobody is on the support desk yet.") if admin.nil?

      conversation = Conversation.open(kind: :admin_customer, admin: admin, customer: current_customer)

      redirect_to shop_conversation_path(conversation)
    end
  end
end
