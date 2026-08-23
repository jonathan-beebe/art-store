class Admin::SellerConversationsController < Admin::BaseController
  def create
    seller = Seller.find(params[:seller_id])
    conversation = Conversation.open(kind: :admin_seller, admin: current_admin, seller: seller)

    redirect_to admin_conversation_path(conversation)
  end
end
