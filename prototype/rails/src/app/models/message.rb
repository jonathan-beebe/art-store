class Message < ApplicationRecord
  BODY_LIMIT = 2_000

  belongs_to :conversation
  belongs_to :sender, polymorphic: true

  scope :oldest_first, -> { order(:created_at, :id) }

  # A message is unread for a reader while nobody has read it and the reader is
  # not the one who sent it. A conversation has two sides, so the reader of a
  # message is always the participant who did not write it and one read_at
  # settles it for both.
  scope :unread_for, ->(reader) {
    where(read_at: nil).where.not(sender_type: reader.class.name, sender_id: reader.id)
  }

  normalizes :body, with: ->(body) { body.strip }

  validates :body,
    presence: { message: "Write a message." },
    length: { maximum: BODY_LIMIT, message: "Keep the message under #{BODY_LIMIT} characters." }
end
