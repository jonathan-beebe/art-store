require "test_helper"

# The parser `config/initializers/rate_limits.rb` runs at boot, tested
# directly rather than by actually rebooting the process: `RateLimits.parse`
# is the pure function the initializer's eager `CONFIG` constant calls, and a
# malformed value raises from it exactly the way it would from the
# initializer.
class RateLimitsTest < ActiveSupport::TestCase
  test "the seven limits documented in docs/alignment.md §3 hold their defaults" do
    assert_equal [ 5, 900 ], counted(:magic_link_request)
    assert_equal [ 20, 900 ], counted(:magic_link_consume)
    assert_equal [ 30, 3_600 ], counted(:message_post)
    assert_equal [ 10, 3_600 ], counted(:conversation_open)
    assert_equal [ 10, 3_600 ], counted(:checkout)
    assert_equal [ 5, 900 ], counted(:payment_attempt)
    assert_equal [ 60, 3_600 ], counted(:listing_write)
  end

  test "every limit is enabled by default" do
    RateLimits::ENV_VARS.each_key { |name| assert_predicate RateLimits.fetch(name), :enabled? }
  end

  test "a count and a seconds window" do
    limit = RateLimits.parse(:checkout, "RATE_LIMIT_CHECKOUT", "100/30s")

    assert_equal 100, limit.count
    assert_equal 30, limit.window_seconds
    assert_predicate limit, :enabled?
  end

  test "a count and a minutes window" do
    limit = RateLimits.parse(:checkout, "RATE_LIMIT_CHECKOUT", "5/15m")

    assert_equal 5, limit.count
    assert_equal 900, limit.window_seconds
  end

  test "a count and an hours window" do
    limit = RateLimits.parse(:checkout, "RATE_LIMIT_CHECKOUT", "20/1h")

    assert_equal 20, limit.count
    assert_equal 3_600, limit.window_seconds
  end

  test "off disables the limit and leaves it uncounted" do
    limit = RateLimits.parse(:checkout, "RATE_LIMIT_CHECKOUT", "off")

    assert_not limit.enabled?
    assert_nil limit.count
    assert_nil limit.window_seconds
  end

  test "a value with no window refuses to boot, naming the variable and the value" do
    error = assert_raises(RateLimits::MalformedValue) { RateLimits.parse(:checkout, "RATE_LIMIT_CHECKOUT", "5") }

    assert_includes error.message, "RATE_LIMIT_CHECKOUT"
    assert_includes error.message, "5"
  end

  test "a value with a trailing slash and nothing after it refuses to boot" do
    assert_raises(RateLimits::MalformedValue) { RateLimits.parse(:checkout, "RATE_LIMIT_CHECKOUT", "5/") }
  end

  test "a value with no count refuses to boot" do
    assert_raises(RateLimits::MalformedValue) { RateLimits.parse(:checkout, "RATE_LIMIT_CHECKOUT", "/15m") }
  end

  test "a value with a unit no window recognises refuses to boot" do
    assert_raises(RateLimits::MalformedValue) { RateLimits.parse(:checkout, "RATE_LIMIT_CHECKOUT", "5/15x") }
  end

  test "a count of zero refuses to boot" do
    assert_raises(RateLimits::MalformedValue) { RateLimits.parse(:checkout, "RATE_LIMIT_CHECKOUT", "0/15m") }
  end

  test "a negative count refuses to boot" do
    assert_raises(RateLimits::MalformedValue) { RateLimits.parse(:checkout, "RATE_LIMIT_CHECKOUT", "-1/15m") }
  end

  test "a value shaped like nothing this parser knows refuses to boot" do
    assert_raises(RateLimits::MalformedValue) { RateLimits.parse(:checkout, "RATE_LIMIT_CHECKOUT", "abc") }
  end

  private

  def counted(name)
    limit = RateLimits.fetch(name)

    [ limit.count, limit.window_seconds ]
  end
end
