require "test_helper"

module Domain
  module Auth
    class LocalRedirectTest < ActiveSupport::TestCase
      ORIGIN = "http://localhost:3000".freeze
      FALLBACK = "/account".freeze

      def test_a_missing_target_falls_back
        assert_equal FALLBACK, resolve(nil)
      end

      def test_a_blank_target_falls_back
        assert_equal FALLBACK, resolve("   ")
      end

      def test_a_root_relative_path_is_kept
        assert_equal "/checkout?step=2", resolve("/checkout?step=2")
      end

      def test_an_absolute_url_on_this_origin_is_kept
        assert_equal "#{ORIGIN}/checkout", resolve("#{ORIGIN}/checkout")
      end

      def test_the_origin_itself_is_kept
        assert_equal ORIGIN, resolve(ORIGIN)
      end

      def test_another_host_falls_back
        assert_equal FALLBACK, resolve("http://evil.example/steal")
      end

      def test_a_host_that_only_prefixes_this_origin_falls_back
        assert_equal FALLBACK, resolve("#{ORIGIN}.evil.example/steal")
      end

      def test_a_protocol_relative_url_falls_back
        assert_equal FALLBACK, resolve("//evil.example/steal")
      end

      def test_a_backslash_escaped_path_falls_back
        assert_equal FALLBACK, resolve("/\\evil.example/steal")
      end

      def test_a_target_carrying_a_newline_falls_back
        assert_equal FALLBACK, resolve("/checkout\nSet-Cookie: x=1")
      end

      def test_keep_if_local_returns_a_local_target
        assert_equal "/checkout", LocalRedirect.keep_if_local("/checkout", origin: ORIGIN)
      end

      def test_keep_if_local_drops_a_foreign_target
        assert_nil LocalRedirect.keep_if_local("http://evil.example/steal", origin: ORIGIN)
      end

      private

      def resolve(requested)
        LocalRedirect.resolve(requested, fallback: FALLBACK, origin: ORIGIN)
      end
    end
  end
end
