# The messaging surface a seller, a customer and an admin all carry: the
# threads they are a participant in and the messages they wrote.
module Messaging
  extend ActiveSupport::Concern

  included do
    has_many :conversations, dependent: :destroy
    has_many :sent_messages, class_name: "Message", as: :sender, dependent: :destroy
  end

  # What the badge on every layout's Messages link counts.
  def unread_message_count
    Message.unread_for(self).where(conversation: conversations).count
  end
end
