class OrderItem < ApplicationRecord
  prefixed_id :oit

  belongs_to :order
  belongs_to :listing
  belongs_to :seller
end
