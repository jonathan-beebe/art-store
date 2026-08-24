module Auth
  class BaseController < ApplicationController
    include SellerAuthentication
    include CustomerIdentity
    include AdminAuthentication
    include MagicLinkSender
  end
end
