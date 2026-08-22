class Order < ApplicationRecord
  belongs_to :customer
  has_many :items, class_name: "OrderItem", dependent: :destroy, inverse_of: :order
  has_many :fulfillments, dependent: :destroy
  has_many :payments, dependent: :destroy

  enum :status, Domain::Orders::OrderStatus::ALL.index_by(&:to_sym)

  def total
    Domain::Money.from_cents(total_cents)
  end
end
