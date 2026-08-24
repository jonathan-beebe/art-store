class Admin::StatsController < Admin::BaseController
  def show
    @page_views_by_day = PageViewCount.by_day
    @page_views_by_pattern = PageViewCount.by_pattern
    @listing_event_tallies = Tally.over(ListingEvent.event_types.keys, ListingEvent.group(:event_type).count)
  end
end
