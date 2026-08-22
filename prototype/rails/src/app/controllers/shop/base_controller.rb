module Shop
  class BaseController < ApplicationController
    include CustomerAuthentication

    before_action :resolve_customer_identity

    layout "shop"
  end
end
