class OrderItem < ApplicationRecord
  belongs_to :order
  belongs_to :listing
  belongs_to :seller
end
