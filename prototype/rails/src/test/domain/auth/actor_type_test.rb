require "test_helper"

module Domain
  module Auth
    class ActorTypeTest < ActiveSupport::TestCase
      test "each actor is named for its side of the marketplace" do
        assert_equal "seller", ActorType::SELLER.name
        assert_equal "customer", ActorType::CUSTOMER.name
      end

      test "each actor lands on its own site" do
        assert_equal :seller_root, ActorType::SELLER.home_route
        assert_equal :shop_account, ActorType::CUSTOMER.home_route
      end

      test "each actor signs in on its own site" do
        assert_equal :seller_login, ActorType::SELLER.login_route
        assert_equal :customer_login, ActorType::CUSTOMER.login_route
      end

      test "only the seller answers to seller" do
        assert ActorType::SELLER.seller?
        refute ActorType::CUSTOMER.seller?
      end

      test "named finds an actor by its stored name" do
        assert_equal ActorType::CUSTOMER, ActorType.named("customer")
      end

      test "named rejects an unknown name" do
        assert_raises(ArgumentError) { ActorType.named("admin") }
      end

      test "all holds both actors" do
        assert_equal [ActorType::SELLER, ActorType::CUSTOMER], ActorType::ALL
      end
    end
  end
end
