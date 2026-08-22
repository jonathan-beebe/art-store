# Runs without Rails: ruby -Iapp app/domain/auth/magic_link_status_test.rb
require "minitest/autorun"
require "time"
require_relative "magic_link_status"

module Domain
  module Auth
    class MagicLinkStatusTest < Minitest::Test
      EXPIRES_AT = Time.utc(2026, 8, 22, 12, 15)

      def test_a_fresh_unconsumed_link_is_usable
        assert_equal MagicLinkStatus::USABLE, status_at(Time.utc(2026, 8, 22, 12, 0))
      end

      def test_a_link_expires_the_moment_now_reaches_the_expiry
        assert_equal MagicLinkStatus::EXPIRED, status_at(EXPIRES_AT)
      end

      def test_a_link_is_expired_after_the_expiry
        assert_equal MagicLinkStatus::EXPIRED, status_at(EXPIRES_AT + 1)
      end

      def test_a_consumed_link_is_consumed
        assert_equal MagicLinkStatus::CONSUMED,
          status_at(Time.utc(2026, 8, 22, 12, 6), consumed_at: Time.utc(2026, 8, 22, 12, 5))
      end

      def test_consumption_outranks_expiry
        assert_equal MagicLinkStatus::CONSUMED,
          status_at(Time.utc(2026, 8, 22, 13, 0), consumed_at: Time.utc(2026, 8, 22, 12, 5))
      end

      private

      def status_at(now, consumed_at: nil)
        MagicLinkStatus.of(expires_at: EXPIRES_AT, consumed_at: consumed_at, now: now)
      end
    end
  end
end
