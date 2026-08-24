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

    @faq = faq || ListingFaq.draft_from(@conversation)
  end
end
