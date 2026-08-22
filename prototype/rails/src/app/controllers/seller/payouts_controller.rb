class Seller::PayoutsController < Seller::BaseController
  # The debug control behind "Run weekly payout now": it settles every seller's
  # released escrow for the last completed week, not just this seller's.
  def create
    summary = Domain::Reports::PayoutSummary.of(
      Escrow::RunWeeklyPayout.new.call(as_of: Time.current).map(&:amount)
    )

    redirect_to seller_earnings_path,
      notice: "Weekly payout run: #{summary.count} payout(s) totalling #{summary.total.format}."
  end
end
