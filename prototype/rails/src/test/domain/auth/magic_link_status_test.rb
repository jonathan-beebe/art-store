require "test_helper"

module Domain
  module Auth
    class MagicLinkStatusTest < ActiveSupport::TestCase
      EXPIRES_AT = Time.utc(2026, 8, 22, 12, 15)

      test "a fresh unconsumed link is usable" do
        assert_equal MagicLinkStatus::USABLE, status_at(Time.utc(2026, 8, 22, 12, 0))
      end

      test "a link expires the moment now reaches the expiry" do
        assert_equal MagicLinkStatus::EXPIRED, status_at(EXPIRES_AT)
      end

      test "a link is expired after the expiry" do
        assert_equal MagicLinkStatus::EXPIRED, status_at(EXPIRES_AT + 1)
      end

      test "a consumed link is consumed" do
        assert_equal MagicLinkStatus::CONSUMED,
          status_at(Time.utc(2026, 8, 22, 12, 6), consumed_at: Time.utc(2026, 8, 22, 12, 5))
      end

      test "consumption outranks expiry" do
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
