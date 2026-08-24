class Admin::LedgerController < Admin::BaseController
  def index
    @seller_id = id_filter(:seller, :sel)
    @entry_type = filter_from(:type, LedgerEntry.entry_types.keys)
    filtered = LedgerEntry.for_seller(@seller_id).with_type(@entry_type)

    @entries = filtered.includes(:seller).order(occurred_at: :desc, id: :desc)
    @totals = filtered.balance
    @sellers = Seller.order(:created_at, :id)
  end
end
