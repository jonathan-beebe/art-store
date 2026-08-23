class Admin::CustomerConversationsController < Admin::BaseController
  def create
    customer = Customer.verified.find(params[:customer_id])
    conversation = Conversation.open(kind: :admin_customer, admin: current_admin, customer: customer)

    redirect_to admin_conversation_path(conversation)
  end
end
