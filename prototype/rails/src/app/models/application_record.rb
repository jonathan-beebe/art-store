class ApplicationRecord < ActiveRecord::Base
  include PrefixedId

  primary_abstract_class
end
