module ApplicationHelper
  # Listings, orders, and fulfillments store their status snake_case; both sites
  # read one back as a sentence.
  def status_label(status)
    status.to_s.humanize
  end

  def money(cents)
    Money.from_cents(cents).format
  end

  # What a customer's row says about them. The block comes first, since it is
  # what an operator reading the table is looking for.
  def customer_standing(customer)
    return "Blocked" if customer.blocked?
    return "Anonymous" if customer.anonymous?

    "Verified"
  end
end
