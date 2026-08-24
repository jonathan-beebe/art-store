class Seller::SupportsController < Seller::BaseController
  # One thread carries a seller's whole conversation with the desk, so the
  # button reopens it rather than starting another.
  def create
    admin = Admin.on_duty
    return redirect_back_or_to(seller_root_path, alert: "Nobody is on the support desk yet.") if admin.nil?

    conversation = Conversation.open(kind: :admin_seller, admin: admin, seller: current_seller)

    redirect_to seller_conversation_path(conversation)
  end
end
