# The inbox, the thread page, and the reply, on whichever of the three sites is
# showing them. A site says who is reading (`current_participant`) and where it
# draws a thread (`thread_template`); the access rule, the reading and the
# posting are the same on all three.
module MessagingSite
  extend ActiveSupport::Concern
  include ThreadPage

  included do
    rate_limit_guard :message_post, by: -> { current_participant.id }, only: :create
  end

  # Both participants and the subject are read for every row, and the unread
  # counts arrive as one hash, so the page costs the same whether it lists one
  # thread or twenty.
  def index
    @conversations =
      Conversation.involving(current_participant).includes(:subject, :seller, :customer, :admin).to_a
    @unread_counts = Conversation.unread_counts_for(current_participant, @conversations)
  end

  def show
    @conversation = thread(params[:id])
    @conversation.read_by!(current_participant)

    present_thread(Message.new)
  end

  def create
    @conversation = thread(params[:conversation_id])
    @conversation.post!(current_participant, message_params[:body])

    redirect_to @conversation.thread_path_for(current_participant)
  rescue ActiveRecord::RecordInvalid => refusal
    present_thread(refusal.record)

    render thread_template, status: :unprocessable_content
  rescue TransitionError => refusal
    present_thread(Message.new)
    flash.now[:alert] = refusal.message

    render thread_template, status: :unprocessable_content
  end

  private

  # Someone else's thread is not theirs to read, and neither is an id no thread
  # of theirs carries.
  def thread(id)
    Conversation.involving(current_participant).find(id)
  end

  def message_params
    params.expect(message: %i[body])
  end

  # A tripped `message_post` comes back on the same thread page a refused
  # reply does, the sentence standing in for a field error since the trip is
  # not about what the reply said.
  def render_too_many_requests(trip)
    @conversation = thread(params[:conversation_id])
    present_thread(Message.new)
    flash.now[:alert] = rate_limit_message(trip)

    render thread_template, status: :too_many_requests
  end
end
