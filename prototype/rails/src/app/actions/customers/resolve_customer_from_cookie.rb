module Customers
  class ResolveCustomerFromCookie
    # Returns nil when the cookie is absent, unreadable, or points at a customer
    # that no longer exists.
    def call(cookie_value)
      id = Integer(cookie_value, exception: false)
      return nil if id.nil? || id < 1

      Customer.find_by(id: CustomerMerge.where(anonymous_customer_id: id).pick(:customer_id) || id)
    end
  end
end
