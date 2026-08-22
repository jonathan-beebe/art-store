class Customer < ApplicationRecord
  normalizes :email, with: ->(email) { Domain::Auth::EmailAddress.normalize(email) }

  has_many :merges_absorbed, class_name: "CustomerMerge", dependent: :destroy, inverse_of: :customer
  has_many :carts, dependent: :destroy
  has_many :favorites, dependent: :destroy
  has_many :notifications, dependent: :destroy
  has_many :orders, dependent: :restrict_with_error

  scope :verified, -> { where.not(email: nil) }

  def anonymous?
    email.nil?
  end
end
