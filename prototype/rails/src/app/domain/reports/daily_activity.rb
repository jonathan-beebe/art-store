require "date"
require_relative "activity_totals"

module Domain
  module Reports
    DailyActivity = Data.define(:date, :totals) do
      def label
        date.strftime("%b %-d")
      end
    end
  end
end
