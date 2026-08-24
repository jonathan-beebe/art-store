# Whether a response is worth a row in page_view_counts, and which day and
# site it belongs to. Kept pure — plain method/status/content-type in, a
# verdict out — so a controller's after_action and this module's own tests
# ask the same three questions in the same terms.
module PageView
  PORTAL_PREFIXES = { "/seller" => "seller", "/admin" => "admin" }.freeze

  # A GET a visitor actually got a page back from: 2xx, text/html. A
  # redirect, a JSON reply, or a failure is not a page a person read.
  def self.countable?(method:, status:, content_type:)
    method.to_s.upcase == "GET" &&
      (200...300).cover?(status) &&
      content_type.to_s == "text/html"
  end

  # The UTC calendar day a moment falls on, which is what a day's row keys on.
  def self.day(at)
    at.utc.to_date
  end

  # The seven days ending on `today`, not Monday-to-Sunday: a calendar week
  # reads as almost nothing every Monday, and the number exists to be
  # compared with the day before it.
  def self.week(today)
    (today - 6.days)..today
  end

  # Which side of the marketplace a route pattern belongs to. The storefront
  # has no prefix of its own, so it is what a pattern is when no portal
  # claims it — which also keeps a path like /sellers-guide on the storefront
  # where it belongs.
  def self.site_for(pattern)
    prefix = PORTAL_PREFIXES.keys.find do |candidate|
      pattern == candidate || pattern.start_with?("#{candidate}/")
    end

    PORTAL_PREFIXES.fetch(prefix, "shop")
  end
end
