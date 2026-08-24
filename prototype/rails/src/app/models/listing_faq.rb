class ListingFaq < ApplicationRecord
  prefixed_id :faq

  QUESTION_LIMIT = 500
  ANSWER_LIMIT = 2_000

  belongs_to :listing
  # The answer the entry was lifted from, for the kinds of entry that came out
  # of a thread. The entry outlives the thread.
  belongs_to :source_message, class_name: "Message", optional: true

  scope :oldest_first, -> { order(:created_at, :id) }

  normalizes :question, :answer, with: ->(text) { text.strip }

  validates :question,
    presence: { message: "Enter the question." },
    length: { maximum: QUESTION_LIMIT, message: "Keep the question under #{QUESTION_LIMIT} characters." }
  validates :answer,
    presence: { message: "Enter the answer." },
    length: { maximum: ANSWER_LIMIT, message: "Keep the answer under #{ANSWER_LIMIT} characters." }

  # The entry a thread offers to publish: what the customer last asked and
  # what the seller last answered, with that answer as its source. Nil until a
  # question on a listing has both sides.
  def self.draft_from(conversation)
    return nil unless conversation.listing_question?

    question = conversation.latest_message_from(conversation.customer)
    answer = conversation.latest_message_from(conversation.seller)
    return nil if question.nil? || answer.nil?

    new(listing: conversation.subject, question: question.body, answer: answer.body, source_message: answer)
  end

  # Puts one answered question on the listing page for everyone. A row exists
  # only while the entry is published, so the storefront reads the table with
  # no predicate of its own and unpublishing is a destroy.
  def self.publish(listing, question:, answer:, source_message: nil, at: Time.current)
    Story.tell("faq.publish", "publishing an answered question to the listing",
      listing_id: listing.id) do |story|
      entry = create!(
        listing: listing, question: question, answer: answer,
        source_message: source_message, published_at: at
      )

      story.did("published the answered question", listing_id: listing.id, faq_id: entry.id)

      entry
    end
  end

  # Taking an entry down is what unpublishing is: a row exists only while the
  # entry is on the listing.
  def unpublish!
    Story.tell("faq.unpublish", "taking an answered question off the listing",
      listing_id: listing_id, faq_id: id) do |story|
      removed = destroy!

      story.did("took the answered question off the listing", listing_id: listing_id, faq_id: id)

      removed
    end
  end
end
