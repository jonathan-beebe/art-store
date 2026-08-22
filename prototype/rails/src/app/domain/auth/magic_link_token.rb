require "digest"

module Domain
  module Auth
    # Only the digest is stored, so a leaked database row cannot be replayed as
    # a link.
    module MagicLinkToken
      module_function

      def digest(token)
        Digest::SHA256.hexdigest(token.to_s)
      end
    end
  end
end
