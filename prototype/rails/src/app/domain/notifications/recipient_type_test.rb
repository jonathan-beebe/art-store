# Runs without Rails: ruby -Iapp app/domain/notifications/recipient_type_test.rb
require "minitest/autorun"
require_relative "recipient_type"

module Domain
  module Notifications
    class RecipientTypeTest < Minitest::Test
      def test_all_names_both_sides_of_the_marketplace
        assert_equal %w[seller customer], RecipientType::ALL
      end
    end
  end
end
