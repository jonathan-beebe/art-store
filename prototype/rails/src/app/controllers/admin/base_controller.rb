# Compact form on purpose: `Admin` is the model class, so the admin namespace
# nests inside it and cannot be reopened with `module`.
class Admin::BaseController < ApplicationController
  include AdminAuthentication

  layout "admin"

  before_action :require_admin!

  private

  def logged_actor
    current_admin
  end

  # Which side of a conversation the admin site's visitor sits on.
  def current_participant
    current_admin
  end

  # Every filter on a directory page is optional: a select's "All" option
  # submits the field with an empty value, which reads as no filter rather
  # than as a value the filter refuses.
  def optional_filter(name)
    params[name].presence
  end

  # A filter naming one of a fixed set of values, falling back to the value
  # the page carries when nobody has chosen one. Anything else is a query
  # string this site does not answer.
  def filter_from(name, allowed, default: nil)
    value = optional_filter(name)
    return default if value.nil?
    return value if allowed.include?(value)

    raise ActionController::BadRequest, "#{name}=#{value} is not a filter this page offers."
  end

  # A filter naming a row by id. An id of another table's shape narrows to
  # nothing, the same way it answers nothing in a path.
  def id_filter(name, prefix)
    value = optional_filter(name)
    return nil if value.nil?

    id = PrefixedUlid.parse(value, prefix)
    return id unless id.nil?

    raise ActionController::BadRequest, "#{name}=#{value} does not name a #{prefix} row."
  end
end
