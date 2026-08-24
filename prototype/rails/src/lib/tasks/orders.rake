namespace :orders do
  desc "Cancel the orders left unverified past STALE_ORDER_HOURS, handing their stock back"
  task :sweep, [ :as_of ] => :environment do |_task, args|
    as_of = args[:as_of].present? ? Time.zone.parse(args[:as_of]) : Time.current
    before = Order.stale_before(at: as_of)
    cancelled = Order.sweep_stale(before: before)

    puts "Cancelling orders placed before #{before.utc.iso8601}"
    cancelled.each { |order| puts order.id }
    puts cancelled.empty? ? "No order has been sitting unverified that long." : "#{cancelled.size} order(s) cancelled."
  end
end
