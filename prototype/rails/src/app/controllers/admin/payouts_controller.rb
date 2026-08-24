# Payouts are a platform action: this is the one place that runs the weekly
# settlement. The seller portal shows balances and history and offers no
# control that runs one.
class Admin::PayoutsController < Admin::BaseController
  def index
    @seller_id = id_filter(:seller, :sel)
    @payouts = Payout.for_seller(@seller_id).includes(:seller).order(period_start: :desc, id: :desc)
    @sellers = Seller.order(:created_at, :id)
  end

  def create
    payouts = Payout.run_weekly(as_of: as_of)
    total = payouts.sum(Money.zero, &:amount)

    redirect_to admin_payouts_path, notice: "Weekly payout run: #{payouts.size} payout(s) totalling #{total.format}."
  end

  private

  def as_of
    params[:as_of].present? ? Time.zone.parse(params[:as_of]) : Time.current
  end
end
