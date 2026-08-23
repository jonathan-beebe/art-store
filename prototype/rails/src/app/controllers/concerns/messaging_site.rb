# The inbox, the thread page, and the reply, on whichever of the three sites is
# showing them. A site says who is reading (`current_participant`) and where it
# draws a thread (`thread_template`); the access rule, the reading and the
# posting are the same on all three.
module MessagingSite
  extend ActiveSupport::Concern
  include ThreadPage

  def index
    @conversations = Conversation.involving(current_participant).includes(:subject)
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
end
