namespace :payouts do
  desc "Pay every seller the escrow released in the Monday-to-Sunday week that just ended"
  task :run, [:as_of] => :environment do |_task, args|
    as_of = args[:as_of].present? ? Time.zone.parse(args[:as_of]) : Time.current
    period = Domain::Escrow::PayoutPeriod.ending_before(as_of)
    payouts = Escrow::RunWeeklyPayout.new.call(as_of: as_of)

    puts "Payout period #{period.label}"
    payouts.each { |payout| puts "#{payout.seller.shop_name} #{payout.amount.format}" }
    puts payouts.empty? ? "No seller has a released balance for this period." : "#{payouts.size} seller(s) paid."
  end
end
