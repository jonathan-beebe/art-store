# One admin's decision to take shopping and messaging away from a customer.
# Browsing, favorites, and reading threads are untouched — see
# `Customer#can_shop?`, the one predicate a block turns off.
class CustomerBlock < ApplicationRecord
  prefixed_id :blk

  belongs_to :customer
  belongs_to :admin

  validates :reason, presence: true, length: { maximum: 500 }

  scope :active, -> { where(lifted_at: nil) }

  def active?
    lifted_at.nil?
  end
end
