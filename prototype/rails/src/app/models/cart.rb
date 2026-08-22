class Cart < ApplicationRecord
  belongs_to :customer
  has_many :items, class_name: "CartItem", dependent: :destroy, inverse_of: :cart

  def lines
    items.map(&:to_line)
  end
end
