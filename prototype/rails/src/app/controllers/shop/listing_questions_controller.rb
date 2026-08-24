module Shop
  # A question asked from a listing page. The identity behind the request is
  # the participant whether or not it carries a verified address, so a visitor
  # can ask without an account and keeps the thread when they verify one.
  class ListingQuestionsController < BaseController
    rate_limit_guard :conversation_open, by: -> { current_participant.id }, only: :create

    def create
      listing = Listing.on_storefront.find_by!(slug: params[:slug])

      redirect_to shop_conversation_path(ask(listing, question_params[:body]))
    rescue ActiveRecord::RecordInvalid => refusal
      redirect_to shop_listing_path(slug: params[:slug]), alert: refusal.record.errors[:body].first
    rescue TransitionError => refusal
      redirect_to shop_listing_path(slug: params[:slug]), alert: refusal.message
    end

    private

    # A tripped `conversation_open` comes back on the listing page the
    # question form sits on, the sentence standing in for a field error.
    def render_too_many_requests(trip)
      @listing = Listing.on_storefront.includes(:seller).find_by!(slug: params[:slug])
      @purchasable = @listing.purchasable?
      @favorited = current_customer.favorited?(@listing)
      @faqs = @listing.faqs.oldest_first
      flash.now[:alert] = rate_limit_message(trip)

      render "shop/listings/show", status: :too_many_requests
    end

    # A refused question leaves no thread behind, so an empty body puts no
    # empty row in either inbox.
    def ask(listing, body)
      Conversation.transaction do
        conversation = Conversation.open(
          kind: :listing_question, subject: listing,
          seller: listing.seller, customer: current_customer
        )
        conversation.post!(current_customer, body)

        conversation
      end
    end

    def question_params
      params.expect(message: %i[body])
    end
  end
end
