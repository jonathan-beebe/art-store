module ApplicationHelper
  # Listings, orders, and fulfillments store their status snake_case; both sites
  # read one back as a sentence.
  def status_label(status)
    status.to_s.humanize
  end
end
