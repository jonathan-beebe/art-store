class Admin::AccountingController < Admin::BaseController
  def show
    @accounts = SellerAccount.for_every_seller
    @money = PlatformMoney.fold
  end
end
