module Auth
  class BaseController < ApplicationController
    include SellerAuthentication
    include CustomerIdentity
    include MagicLinkSender
  end
end
