class ListingFaq < ApplicationRecord
  QUESTION_LIMIT = 500
  ANSWER_LIMIT = 2_000

  belongs_to :listing
  # The answer the entry was lifted from, for the kinds of entry that came out
  # of a thread. The entry outlives the thread.
  belongs_to :source_message, class_name: "Message", optional: true

  normalizes :question, :answer, with: ->(text) { text.strip }

  validates :question,
    presence: { message: "Enter the question." },
    length: { maximum: QUESTION_LIMIT, message: "Keep the question under #{QUESTION_LIMIT} characters." }
  validates :answer,
    presence: { message: "Enter the answer." },
    length: { maximum: ANSWER_LIMIT, message: "Keep the answer under #{ANSWER_LIMIT} characters." }

  # Puts one answered question on the listing page for everyone. A row exists
  # only while the entry is published, so the storefront reads the table with
  # no predicate of its own and unpublishing is a destroy.
  def self.publish(listing, question:, answer:, source_message: nil, at: Time.current)
    create!(
      listing: listing, question: question, answer: answer,
      source_message: source_message, published_at: at
    )
  end
end
