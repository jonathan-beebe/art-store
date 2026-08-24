class Admin::DashboardController < Admin::BaseController
  def show
    @seller_count = Seller.count
    @verified_customer_count = Customer.verified.count
    @anonymous_customer_count = Customer.anonymous.count
    @listing_tallies = Tally.over(Listing.statuses.keys, Listing.group(:status).count)
    @order_tallies = Tally.over(Order.statuses.keys, Order.group(:status).count)
    @fulfillment_tallies = Tally.over(Fulfillment.statuses.keys, Fulfillment.group(:status).count)
    @money = PlatformMoney.fold
    @page_views_this_week = PageViewCount.total_for(PageView.week(Date.current))
  end
end
