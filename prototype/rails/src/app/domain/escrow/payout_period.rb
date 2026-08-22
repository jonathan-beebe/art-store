require "date"

module Domain
  module Escrow
    # The Monday-to-Sunday week a payout run settles: the most recently
    # completed one as of a given moment.
    PayoutPeriod = Data.define(:first_day, :last_day) do
      def self.ending_before(as_of)
        date = as_of.to_date
        first_day = date - ((date.wday - 1) % 7) - 7

        new(first_day: first_day, last_day: first_day + 6)
      end

      # Everything dated at or before this instant belongs to the period, which
      # is what makes a second run of the same period a no-op.
      def ends_at
        Time.utc(last_day.year, last_day.month, last_day.day, 23, 59, 59)
      end

      def covers?(moment)
        (first_day..last_day).cover?(moment.to_date)
      end

      def label
        "#{first_day} to #{last_day}"
      end
    end
  end
end
