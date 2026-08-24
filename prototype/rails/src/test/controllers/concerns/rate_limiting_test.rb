require "test_helper"

# `rate_limit_client_ip` directly, over a stubbed `request` rather than a
# live one. `docs/alignment.md` §3's "set" branch — TRUSTED_PROXIES set, the
# first trusted proxy header decides the ip — cannot be driven through a
# real HTTP request in this suite: `Rails::Engine#app` memoizes the
# middleware stack, and `ActionDispatch::RemoteIp#initialize` captures its
# trusted-proxy list once when that stack is first built, so mutating
# `config.action_dispatch.trusted_proxies` (or the `TRUSTED_PROXIES` env var
# it is drawn from) after boot never reaches `request.remote_ip`. This test
# owns that branch instead, calling the method with a `request` double that
# reports different `remote_addr`/`remote_ip` values, the same two facts
# `ActionDispatch::RemoteIp` would give a real request when the peer is (or
# is not) a trusted proxy.
#
# What this proves: `rate_limit_client_ip`'s own branch on
# `ENV["TRUSTED_PROXIES"].present?` reads `remote_addr` when unset and
# `remote_ip` when set. What it does not prove: that Rails' own
# `ActionDispatch::RemoteIp`, wired from `TRUSTED_PROXIES` at a real process
# boot, computes `remote_ip` correctly for a given proxy list — that is
# Rails' own tested behaviour, not this app's code. The unset branch is also
# covered end to end, over a real request, by
# `test/rate_limiting_test.rb`'s "TRUSTED_PROXIES unset" test.
class RateLimitingConcernTest < ActiveSupport::TestCase
  include RateLimiting

  FakeRequest = Struct.new(:remote_addr, :remote_ip)

  test "TRUSTED_PROXIES unset reads the socket's own peer" do
    stub_request(remote_addr: "203.0.113.9", remote_ip: "198.51.100.1")

    with_trusted_proxies(nil) { assert_equal "203.0.113.9", rate_limit_client_ip }
  end

  test "TRUSTED_PROXIES set reads the first trusted proxy header instead" do
    stub_request(remote_addr: "203.0.113.9", remote_ip: "198.51.100.1")

    with_trusted_proxies("198.51.100.1/32") { assert_equal "198.51.100.1", rate_limit_client_ip }
  end

  private

  def stub_request(remote_addr:, remote_ip:)
    @request = FakeRequest.new(remote_addr, remote_ip)
  end

  def request
    @request
  end

  def with_trusted_proxies(value)
    original = ENV["TRUSTED_PROXIES"]
    ENV["TRUSTED_PROXIES"] = value
    yield
  ensure
    ENV["TRUSTED_PROXIES"] = original
  end
end

# `redacted_rate_limit_key`, the pure string transform behind the
# `rate_limit.exceed` log line's `key` field, tested directly rather than
# through a full HTTP trip: driving the "same address twice" property
# through `test/rate_limiting_test.rb`'s trip helper would trip the
# email-keyed limit on the second call's very first request (the address's
# own counter is already past its limit from the first), producing more
# than one log line and defeating a `.sole` assertion on either call.
class RedactedRateLimitKeyTest < ActiveSupport::TestCase
  include RateLimiting

  test "a non-email key is returned as-is" do
    assert_equal "cus_01J5X3M9A2K8YB7Q4R6T1V0WZE", redacted_rate_limit_key("cus_01J5X3M9A2K8YB7Q4R6T1V0WZE")
    assert_equal "203.0.113.9", redacted_rate_limit_key("203.0.113.9")
  end

  test "an email key is rendered as a labelled, truncated digest" do
    assert_match(/\Asha256:[0-9a-f]{16}\z/, redacted_rate_limit_key("secret@example.com"))
  end

  test "the same address digests the same way twice" do
    assert_equal redacted_rate_limit_key("repeat@example.com"), redacted_rate_limit_key("repeat@example.com")
  end

  test "two different addresses digest differently" do
    refute_equal redacted_rate_limit_key("one@example.com"), redacted_rate_limit_key("two@example.com")
  end

  test "the address itself never appears in the digest" do
    email = "secret@example.com"

    refute_includes redacted_rate_limit_key(email), email
  end
end
