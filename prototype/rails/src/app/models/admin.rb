class Admin < ApplicationRecord
  include EmailAddress

  has_many :notifications, as: :recipient, dependent: :destroy

  # Admin rows are seeded, so a verified link signs in an operator who is
  # already there. An address with no row claims nothing and reaches no session.
  def self.claim(email)
    find_by(email: email)
  end
end
