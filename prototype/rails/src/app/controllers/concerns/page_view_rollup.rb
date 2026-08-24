# Rolls one page view into `page_view_counts` after every response, over the
# whole app rather than one site at a time: included once on
# `ApplicationController`, so no site can forget it the way a per-route
# concern could. The pattern comes off the route Rails actually matched
# rather than the concrete URL, so the table grows with routes and days, not
# with traffic — and a request that matched no route never reaches here at
# all, which is the same as counting it against nothing.
module PageViewRollup
  extend ActiveSupport::Concern

  included do
    after_action :roll_up_page_view
  end

  private

  # `request.method` reads the method as the client sent it; `request_method`
  # reads it after Rails has rewritten a `HEAD` to a `GET` to run the `GET`
  # action, which is right for routing a `HEAD` but wrong for counting one as
  # a page a person read.
  def roll_up_page_view
    countable = PageView.countable?(
      method: request.method, status: response.status, content_type: response.media_type
    )
    return unless countable

    pattern = request.route_uri_pattern
    return if pattern.nil?

    PageViewCount.record!(path_pattern: pattern.sub(/\(\.:format\)\z/, ""))
  end
end
