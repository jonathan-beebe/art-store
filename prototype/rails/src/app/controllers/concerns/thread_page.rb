# What every page that draws a thread reads: the conversation from oldest
# message down, and the reply being written. A page that comes back holding a
# refused record passes that record in, so the seller sees their own text.
module ThreadPage
  extend ActiveSupport::Concern

  private

  def present_thread(message)
    @message = message
    @messages = @conversation.messages.oldest_first.includes(:sender)
  end
end
