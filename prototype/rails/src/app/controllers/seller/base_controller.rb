# Compact form on purpose: `Seller` is the model class, so the portal
# namespace nests inside it and cannot be reopened with `module`.
class Seller::BaseController < ApplicationController
  include SellerAuthentication

  layout "seller"
end
