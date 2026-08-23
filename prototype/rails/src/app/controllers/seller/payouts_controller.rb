class Seller::PayoutsController < Seller::BaseController
  # The debug control behind "Run weekly payout now": it settles every seller's
  # released escrow for the last completed week, not just this seller's.
  def create
    payouts = Payout.run_weekly
    total = payouts.sum(Money.zero, &:amount)

    redirect_to seller_earnings_path,
      notice: "Weekly payout run: #{payouts.size} payout(s) totalling #{total.format}."
  end
end
