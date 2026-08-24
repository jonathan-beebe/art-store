# Whether a cart or order may become (or stay) a placed sale, and which lines
# stand in the way when it may not. Built from anything that responds to
# `listing` and `quantity` — a cart item at checkout, an order item at a
# retried charge — so one classifier serves both callers.
class OrderPlacement
  Line = Data.define(:listing_id, :title, :status, :available_quantity, :quantity, :removed)
  BlockedLine = Data.define(:listing_id, :title, :reason)

  NOTICES = {
    removed: "no longer available",
    off_sale: "no longer for sale",
    sold_out: "sold out",
    short_stock: "no longer in stock in that quantity"
  }.freeze

  class << self
    def plan(items)
      new(items)
    end

    # Locks the listing every item in +items+ is about, in ascending id
    # order — a stable order so two callers locking the same listings cannot
    # deadlock on each other — and points each item's cached listing at the
    # locked row. Call inside the transaction that will act on the plan,
    # before building it, so the plan and whatever takes or restores stock
    # afterward read the same rows.
    def lock_listings(items)
      items = items.to_a
      listing_ids = items.map(&:listing_id).uniq
      return items if listing_ids.empty?

      locked = Listing.lock.where(id: listing_ids).order(:id).index_by(&:id)
      items.each { |item| item.association(:listing).target = locked.fetch(item.listing_id) }

      items
    end

    # Which reason blocks a line, or nil when nothing does. A removal
    # outranks whatever the status says; sold outranks a bare quantity of
    # zero reading as merely out of stock.
    def reason_for(line)
      return :removed if line.removed
      return :sold_out if line.status == "sold"
      return :off_sale if line.status != "for_sale"
      return :sold_out if line.available_quantity < 1
      :short_stock if line.quantity > line.available_quantity
    end

    def notice_for(reason)
      NOTICES.fetch(reason)
    end

    # The blocked lines' listing id, title, and reason, shaped for a log line.
    def log_payload(blocked_lines)
      blocked_lines.map { |line| { listing_id: line.listing_id, title: line.title, reason: line.reason } }
    end

    def line_for(item)
      listing = item.listing

      Line.new(
        listing_id: listing.id, title: listing.title, status: listing.status,
        available_quantity: listing.quantity, quantity: item.quantity,
        removed: listing.actively_removed?
      )
    end
  end

  attr_reader :items

  def initialize(items)
    @items = items
  end

  def ok?
    blocked_lines.empty?
  end

  def blocked_lines
    @blocked_lines ||= items.filter_map { |item| blocked_line(item) }
  end

  private

  def blocked_line(item)
    line = self.class.line_for(item)
    reason = self.class.reason_for(line)
    return nil if reason.nil?

    BlockedLine.new(listing_id: line.listing_id, title: line.title, reason: reason)
  end
end
