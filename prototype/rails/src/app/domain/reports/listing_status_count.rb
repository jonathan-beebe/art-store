require_relative "status_label"

module Domain
  module Reports
    ListingStatusCount = Data.define(:status, :count) do
      def label
        StatusLabel.of(status)
      end
    end
  end
end
