# The answered questions published on one listing. A row exists only while
# the entry is published, so unpublishing is a destroy.
class Seller::FaqsController < Seller::BaseController
  include SellerThreadPage

  before_action :set_listing

  def index
    present_entries(ListingFaq.new)
  end

  def create
    ListingFaq.publish(
      @listing,
      question: faq_params[:question], answer: faq_params[:answer], source_message: source_message
    )

    redirect_to seller_listing_faqs_path(@listing), notice: "Published to the listing."
  rescue ActiveRecord::RecordInvalid => refusal
    render_refusal(refusal.record)
  end

  def update
    entry = @listing.faqs.find(params[:id])

    if entry.update(faq_params.slice(:question, :answer))
      return redirect_to seller_listing_faqs_path(@listing), notice: "The entry is updated.", status: :see_other
    end

    present_entries(ListingFaq.new, edited: entry)

    render :index, status: :unprocessable_content
  end

  def destroy
    @listing.faqs.find(params[:id]).unpublish!

    redirect_to seller_listing_faqs_path(@listing), notice: "Unpublished from the listing.", status: :see_other
  end

  private

  def set_listing
    @listing = current_seller.listings.find(params[:listing_id])
  end

  # A refused entry comes back where it was written: the thread the answer was
  # lifted from, or this listing's FAQ page.
  def render_refusal(draft)
    @conversation = draft.source_message&.conversation

    if @conversation.nil?
      present_entries(draft)

      return render :index, status: :unprocessable_content
    end

    present_thread(Message.new, faq: draft)

    render SellerThreadPage::TEMPLATE, status: :unprocessable_content
  end

  # What the page reads: the entry being written and the ones already up. A
  # refused edit takes the place of the row it was edited from, so the form
  # comes back holding the seller's own text.
  def present_entries(draft, edited: nil)
    @faq = draft
    @faqs = @listing.faqs.oldest_first.map { |entry| entry.id == edited&.id ? edited : entry }
  end

  # An entry may be written from scratch, and an answer from outside this
  # listing's threads is not this seller's to publish.
  def source_message
    id = faq_params[:source_message_id]
    return nil if id.blank?

    Message.where(conversation: @listing.conversations).find(id)
  end

  def faq_params
    params.expect(listing_faq: %i[question answer source_message_id])
  end
end
