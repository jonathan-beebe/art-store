# One route pattern's traffic on one day, on one side of the marketplace.
# Rolled up at response time rather than read back from a row per hit: the
# table grows with routes and days, not with traffic.
class PageViewCount < ApplicationRecord
  prefixed_id :pvc

  SITES = %w[shop seller admin].freeze

  validates :site, inclusion: { in: SITES }
  validates :path_pattern, presence: true
  validates :day, presence: true

  # Rolls one hit into its (site, path_pattern, day) row in one statement,
  # with no read first: the unique index is what makes the first hit of a
  # day an insert and every later one an increment.
  def self.record!(path_pattern:, at: Time.current)
    upsert(
      {
        id: PrefixedUlid.generate(:pvc),
        site: PageView.site_for(path_pattern),
        path_pattern: path_pattern,
        day: PageView.day(at),
        count: 1
      },
      unique_by: %i[site path_pattern day],
      on_duplicate: Arel.sql("count = count + 1")
    )
  end

  # Every day that saw traffic, newest first.
  def self.by_day
    group(:day).sum(:count).sort_by { |day, _count| day }.reverse
  end

  # Every route pattern that saw traffic, busiest first.
  def self.by_pattern
    group(:site, :path_pattern).sum(:count).sort_by { |(site, pattern), count| [ -count, site, pattern ] }
  end

  # How many hits a run of days holds, folded in one statement.
  def self.total_for(days)
    where(day: days).sum(:count)
  end
end
