module Auth
  class BaseController < ApplicationController
    include SellerAuthentication
    include CustomerIdentity

    private

    def send_magic_link(email:, actor_type:, redirect_to: nil)
      SendMagicLink
        .new(delivery: MagicLinkDelivery.build(flash), link_url: ->(token) { verify_magic_link_url(token) })
        .call(email: email, actor_type: actor_type, redirect_to: redirect_to)
    end
  end
end
