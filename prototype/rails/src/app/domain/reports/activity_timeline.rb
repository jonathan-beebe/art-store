require "date"
require_relative "activity_totals"
require_relative "daily_activity"

module Domain
  module Reports
    # A gapless run of days ending on the day of ends_on, oldest first, so the
    # breakdown keeps a row for every day a seller looks at.
    module ActivityTimeline
      module_function

      def last_days(counts_by_date, ends_on:, days:)
        raise ArgumentError, "a timeline covers at least one day, got #{days}" if days < 1

        last_day = ends_on.to_date

        ((last_day - (days - 1))..last_day).map do |day|
          DailyActivity.new(date: day, totals: ActivityTotals.from(counts_by_date.fetch(day, {})))
        end
      end
    end
  end
end
