require "test_helper"

module Domain
  module Auth
    class ActorTypeTest < ActiveSupport::TestCase
      def test_each_actor_is_named_for_its_side_of_the_marketplace
        assert_equal "seller", ActorType::SELLER.name
        assert_equal "customer", ActorType::CUSTOMER.name
      end

      def test_each_actor_lands_on_its_own_site
        assert_equal :seller_root, ActorType::SELLER.home_route
        assert_equal :shop_account, ActorType::CUSTOMER.home_route
      end

      def test_each_actor_signs_in_on_its_own_site
        assert_equal :seller_login, ActorType::SELLER.login_route
        assert_equal :customer_login, ActorType::CUSTOMER.login_route
      end

      def test_only_the_seller_answers_to_seller
        assert ActorType::SELLER.seller?
        refute ActorType::CUSTOMER.seller?
      end

      def test_named_finds_an_actor_by_its_stored_name
        assert_equal ActorType::CUSTOMER, ActorType.named("customer")
      end

      def test_named_rejects_an_unknown_name
        assert_raises(ArgumentError) { ActorType.named("admin") }
      end

      def test_all_holds_both_actors
        assert_equal [ActorType::SELLER, ActorType::CUSTOMER], ActorType::ALL
      end
    end
  end
end
