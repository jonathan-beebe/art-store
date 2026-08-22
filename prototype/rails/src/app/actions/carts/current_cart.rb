module Carts
  class CurrentCart
    # A merge hands the verified customer whatever cart the anonymous visitor
    # was filling, so one customer can own two. The one holding the most items
    # is the one they were shopping with.
    def call(customer:)
      customer.carts.includes(:items).max_by { |cart| [cart.items.size, cart.id] } || customer.carts.create!
    end
  end
end
