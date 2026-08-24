# The primary key is the public id: a row mints one as it is built, and there
# is no second column to look a row up by.
module PrefixedId
  extend ActiveSupport::Concern

  class_methods do
    # The three letters this table's ids carry.
    def prefixed_id(prefix)
      attribute :id, default: -> { PrefixedUlid.generate(prefix) }
    end
  end
end
