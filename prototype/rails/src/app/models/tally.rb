# Every key a domain enum names, in the enum's own order, paired with the
# count a group-by measured for it — 0 for a key nothing has reached. A
# `group by` answers only for the keys that have rows, and a status nobody
# has reached is still a status the dashboard shows.
module Tally
  def self.over(keys, counted)
    keys.index_with { |key| counted.fetch(key.to_s, 0) }
  end
end
