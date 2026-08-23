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

  # The element every layout gives this actor's badge, and the target a change
  # to their count replaces.
  def unread_badge_dom_id
    ActionView::RecordIdentifier.dom_id(self, :unread_messages)
  end

  # The badge belongs to one actor on their own site, so their count travels on
  # a stream nobody else is handed a signed name for.
  def broadcast_unread_message_count
    Turbo::StreamsChannel.broadcast_replace_to(
      [self, :unread_messages],
      target: unread_badge_dom_id,
      partial: "#{Conversation.site_of(self)}/conversations/unread_badge",
      locals: { actor: self }
    )
  end
end
