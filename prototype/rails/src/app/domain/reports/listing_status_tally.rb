require_relative "listing_status_count"

module Domain
  module Reports
    # Every listing status a seller owns, in lifecycle order, so the dashboard
    # keeps its tiles in place on a day nothing sold.
    module ListingStatusTally
      module_function

      def from(counts_by_status)
        ::Listing.statuses.keys.map do |status|
          ListingStatusCount.new(status: status, count: counts_by_status.fetch(status, 0))
        end
      end

      def total(tally)
        tally.sum(&:count)
      end
    end
  end
end
