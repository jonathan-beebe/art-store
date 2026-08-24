require "test_helper"

# The headers every response carries, on all three sites: the policy in
# `config/initializers/content_security_policy.rb`, and `X-Content-Type-
# Options` / `Referrer-Policy`, which Rails sets by default
# (`config.action_dispatch.default_headers`) and this suite only confirms
# survive a real request rather than re-declaring.
class SecurityHeadersTest < ActionDispatch::IntegrationTest
  test "the storefront carries the policy" do
    get root_path

    assert_headers
  end

  test "the seller portal carries the policy" do
    get seller_login_path

    assert_headers
  end

  test "the admin site carries the policy" do
    get admin_login_path

    assert_headers
  end

  test "importmap's inline script tags carry the nonce the policy requires" do
    get root_path

    nonce = response.headers["Content-Security-Policy"][/script-src 'self' 'nonce-([^']+)'/, 1]
    assert_select "script[type=importmap][nonce=?]", nonce
    assert_select "script[type=module][nonce=?]", nonce
  end

  private

  def assert_headers
    assert_response :success
    assert_equal "nosniff", response.headers["X-Content-Type-Options"]
    assert_equal "strict-origin-when-cross-origin", response.headers["Referrer-Policy"]

    csp = response.headers["Content-Security-Policy"]
    assert_includes csp, "default-src 'self'"
    assert_includes csp, "img-src 'self' data:"
    assert_includes csp, "style-src 'self'"
    assert_match(/script-src 'self' 'nonce-[^']+'/, csp)
    assert_includes csp, "form-action 'self'"
    assert_includes csp, "frame-ancestors 'none'"
  end
end
