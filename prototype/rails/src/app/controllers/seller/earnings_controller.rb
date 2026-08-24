class Seller::EarningsController < Seller::BaseController
  def show
    @fulfillments = current_seller.fulfillments.includes(order: :items).order(created_at: :desc, id: :desc)
    @balance = current_seller.escrow_balance
    @payouts = current_seller.payouts.order(period_start: :desc)
    @movements = current_seller.ledger_entries.order(occurred_at: :desc, id: :desc)
  end
end
