# Compact form on purpose: `Admin` is the model class, so the admin namespace
# nests inside it and cannot be reopened with `module`.
class Admin::BaseController < ApplicationController
  include AdminAuthentication

  layout "admin"

  before_action :require_admin!
end
