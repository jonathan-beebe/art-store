# Which slice of a listing grid a visitor is looking at, and the two numbers
# a query needs to fetch it.
Page = Data.define(:number, :size, :total_count) do
  # +requested+ arrives from a query string, so anything that is not a page
  # of this collection lands on the nearest one that is.
  def self.of(requested:, size:, total_count:)
    raise ArgumentError, "a page holds at least one item, got #{size}" unless size.is_a?(Integer) && size.positive?
    raise ArgumentError, "a count cannot be negative, got #{total_count}" if total_count.negative?

    last = last_number(size: size, total_count: total_count)

    new(number: requested.to_i.clamp(1, last), size: size, total_count: total_count)
  end

  # An empty collection still has a first page, which is where "no art
  # matches that" is written.
  def self.last_number(size:, total_count:)
    [(total_count + size - 1) / size, 1].max
  end

  def offset
    (number - 1) * size
  end

  def limit
    size
  end

  def count
    Page.last_number(size: size, total_count: total_count)
  end

  def first?
    number == 1
  end

  def last?
    number == count
  end

  def previous_number
    number - 1
  end

  def next_number
    number + 1
  end
end
