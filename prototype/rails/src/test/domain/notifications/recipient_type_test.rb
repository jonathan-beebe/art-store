require "test_helper"

module Domain
  module Notifications
    class RecipientTypeTest < ActiveSupport::TestCase
      def test_all_names_both_sides_of_the_marketplace
        assert_equal %w[seller customer], RecipientType::ALL
      end
    end
  end
end
