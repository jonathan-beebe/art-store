module Auth
  class MagicLinksController < BaseController
    REFUSALS = {
      Domain::Auth::MagicLinkStatus::EXPIRED => "That sign-in link has expired. Ask for a new one.",
      Domain::Auth::MagicLinkStatus::CONSUMED => "That sign-in link has already been used. Ask for a new one."
    }.freeze

    UNKNOWN_LINK = "That sign-in link is not valid. Ask for a new one.".freeze

    def show
      link = MagicLink.for_token(params[:token]).first
      return refuse(Domain::Auth::ActorType::CUSTOMER, UNKNOWN_LINK) if link.nil?

      now = Time.current
      refusal = REFUSALS[link.status_at(now)]
      return refuse(link.actor_type, refusal) if refusal

      link.consume!(now)
      sign_in(link)

      redirect_to url_from(link.redirect_to) || path_for(link.actor_type.home_route)
    end

    private

    def sign_in(link)
      if link.actor_type.seller?
        sign_in_seller(ClaimSellerIdentity.new.call(email: link.email))
      else
        sign_in_customer(
          Customers::ClaimCustomerIdentity.new.call(email: link.email, current: customer_from_cookie)
        )
      end
    end

    def refuse(actor_type, message)
      redirect_to path_for(actor_type.login_route), alert: message
    end

    def path_for(route)
      public_send(:"#{route}_path")
    end
  end
end
