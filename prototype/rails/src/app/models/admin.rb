class Admin < ApplicationRecord
  include EmailAddress
  include Messaging

  has_many :notifications, as: :recipient, dependent: :destroy

  # Admin rows are seeded, so a verified link signs in an operator who is
  # already there. An address with no row claims nothing and reaches no session.
  def self.claim(email)
    find_by(email: email)
  end

  # The operator a support thread opens against. Rows are seeded and nothing
  # assigns them, so the desk is the first admin by id.
  def self.on_duty
    order(:id).first
  end

  # An operator is seeded with a name; the address stands in while they have
  # none, the way it does for a seller.
  def display_name
    name.to_s.strip.presence || email.to_s.split("@").first.to_s
  end
end
