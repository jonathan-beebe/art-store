module Domain
  module Auth
    # Which side of the marketplace a magic link signs in, and where that side
    # sends someone before and after they verify.
    class ActorType < Data.define(:name, :home_route, :login_route)
      def self.named(name)
        BY_NAME.fetch(name.to_s) { raise ArgumentError, "unknown actor type: #{name.inspect}" }
      end

      def seller?
        self == SELLER
      end

      def customer?
        self == CUSTOMER
      end

      def to_s
        name
      end

      SELLER = new(name: "seller", home_route: :seller_root, login_route: :seller_login)
      CUSTOMER = new(name: "customer", home_route: :shop_account, login_route: :customer_login)
      ALL = [SELLER, CUSTOMER].freeze
      BY_NAME = ALL.to_h { |actor_type| [actor_type.name, actor_type] }.freeze
    end
  end
end
