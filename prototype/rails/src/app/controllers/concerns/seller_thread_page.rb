# The portal's thread page. It carries the reply form the three sites share
# and, on a question about a listing, a form that publishes the seller's answer
# as an FAQ entry. Both forms come back on this page when a record is refused,
# so each takes the record that was refused.
module SellerThreadPage
  extend ActiveSupport::Concern
  include ThreadPage

  TEMPLATE = "seller/conversations/show".freeze

  private

  def present_thread(message, faq: nil)
    super(message)

    @faq = faq || draft_faq
  end

  # The publish form offers only an answer nobody has published yet. Once its
  # message already carries a `listing_faq`, the thread shows that instead
  # (see the message partial's published marker) and this returns nil. Reads
  # the row directly rather than through `draft.source_message.listing_faq`:
  # `belongs_to :source_message, inverse_of: :listing_faq` on the unsaved
  # draft itself would otherwise wire the association's other end and read
  # the draft back as its own answer's `listing_faq`.
  def draft_faq
    draft = ListingFaq.draft_from(@conversation)
    return nil if draft.nil? || ListingFaq.exists?(source_message_id: draft.source_message_id)

    draft
  end
end
