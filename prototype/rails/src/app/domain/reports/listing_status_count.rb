module Domain
  module Reports
    ListingStatusCount = Data.define(:status, :count) do
      def label
        status.humanize
      end
    end
  end
end
