require "test_helper"

module Domain
  module Auth
    class LocalRedirectTest < ActiveSupport::TestCase
      ORIGIN = "http://localhost:3000".freeze
      FALLBACK = "/account".freeze

      test "a missing target falls back" do
        assert_equal FALLBACK, resolve(nil)
      end

      test "a blank target falls back" do
        assert_equal FALLBACK, resolve("   ")
      end

      test "a root relative path is kept" do
        assert_equal "/checkout?step=2", resolve("/checkout?step=2")
      end

      test "an absolute url on this origin is kept" do
        assert_equal "#{ORIGIN}/checkout", resolve("#{ORIGIN}/checkout")
      end

      test "the origin itself is kept" do
        assert_equal ORIGIN, resolve(ORIGIN)
      end

      test "another host falls back" do
        assert_equal FALLBACK, resolve("http://evil.example/steal")
      end

      test "a host that only prefixes this origin falls back" do
        assert_equal FALLBACK, resolve("#{ORIGIN}.evil.example/steal")
      end

      test "a protocol relative url falls back" do
        assert_equal FALLBACK, resolve("//evil.example/steal")
      end

      test "a backslash escaped path falls back" do
        assert_equal FALLBACK, resolve("/\\evil.example/steal")
      end

      test "a target carrying a newline falls back" do
        assert_equal FALLBACK, resolve("/checkout\nSet-Cookie: x=1")
      end

      test "keep if local returns a local target" do
        assert_equal "/checkout", LocalRedirect.keep_if_local("/checkout", origin: ORIGIN)
      end

      test "keep if local drops a foreign target" do
        assert_nil LocalRedirect.keep_if_local("http://evil.example/steal", origin: ORIGIN)
      end

      private

      def resolve(requested)
        LocalRedirect.resolve(requested, fallback: FALLBACK, origin: ORIGIN)
      end
    end
  end
end
