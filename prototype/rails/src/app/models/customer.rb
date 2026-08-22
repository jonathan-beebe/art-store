class Customer < ApplicationRecord
  normalizes :email, with: ->(email) { Domain::Auth::EmailAddress.normalize(email) }

  has_many :merges_absorbed, class_name: "CustomerMerge", dependent: :destroy, inverse_of: :customer

  scope :verified, -> { where.not(email: nil) }

  def anonymous?
    email.nil?
  end
end
