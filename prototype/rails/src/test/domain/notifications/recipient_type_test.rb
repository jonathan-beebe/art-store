require "test_helper"

module Domain
  module Notifications
    class RecipientTypeTest < ActiveSupport::TestCase
      test "all names both sides of the marketplace" do
        assert_equal %w[seller customer], RecipientType::ALL
      end
    end
  end
end
