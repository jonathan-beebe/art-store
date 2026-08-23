module Auth
  class MagicLinksController < BaseController
    EXPIRED = "That sign-in link has expired. Ask for a new one.".freeze
    CONSUMED = "That sign-in link has already been used. Ask for a new one.".freeze
    UNKNOWN_LINK = "That sign-in link is not valid. Ask for a new one.".freeze

    # Where each side of the marketplace lands, before and after it verifies.
    HOME_PATHS = { "seller" => :seller_root_path, "customer" => :shop_account_path }.freeze
    LOGIN_PATHS = { "seller" => :seller_login_path, "customer" => :customer_login_path }.freeze

    def show
      link = MagicLink.find_by_token(params[:token])
      return refuse("customer", UNKNOWN_LINK) if link.nil?
      return refuse(link.actor_type, refusal_for(link)) unless link.usable?

      link.consume!
      sign_in(link)

      redirect_to url_from(link.redirect_to) || path_for(HOME_PATHS, link.actor_type)
    end

    private

    def sign_in(link)
      if link.seller?
        sign_in_seller(Seller.claim(link.email))
      else
        sign_in_customer(Customer.claim(link.email, current: customer_from_cookie))
      end
    end

    def refusal_for(link)
      link.consumed? ? CONSUMED : EXPIRED
    end

    def refuse(actor_type, message)
      redirect_to path_for(LOGIN_PATHS, actor_type), alert: message
    end

    def path_for(paths, actor_type)
      public_send(paths.fetch(actor_type))
    end
  end
end
