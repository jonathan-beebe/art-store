# The admin site knows every customer, the browsers the storefront is holding
# a cart for included: an anonymous row is who an order was placed by until a
# link is followed for it.
class Admin::CustomersController < Admin::BaseController
  def index
    @standing = filter_from(:standing, Customer::STANDINGS, default: "all")
    @customers = Customer.standing(@standing).directory
  end

  def show
    @customer = Customer.find(params[:id])
    @orders = @customer.orders.includes(:customer, :items, :fulfillments).order(placed_at: :desc, id: :desc)
    @favorites = @customer.favorites.includes(:listing).order(created_at: :desc, id: :desc)
    @cart_items = CartItem.where(cart: @customer.carts).includes(:listing).order(created_at: :desc, id: :desc)
    @blocks = @customer.blocks
    @merges = @customer.merges
  end
end
