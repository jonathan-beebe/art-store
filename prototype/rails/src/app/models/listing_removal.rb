# One admin's decision to take a listing off the storefront, whatever its own
# status says. `kind` is fixed at creation: a temporary removal can be lifted,
# a permanent one cannot, and raising one to the other is lift then remove.
class ListingRemoval < ApplicationRecord
  prefixed_id :rmv

  belongs_to :listing
  belongs_to :admin

  enum :kind, { temporary: "temporary", permanent: "permanent" }, validate: true

  validates :reason, presence: true, length: { maximum: 500 }

  scope :active, -> { where(lifted_at: nil) }

  def active?
    lifted_at.nil?
  end

  def liftable?
    temporary?
  end
end
