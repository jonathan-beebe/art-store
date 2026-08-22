module Domain
  module Auth
    # Whether a magic link may still sign someone in.
    module MagicLinkStatus
      USABLE = :usable
      EXPIRED = :expired
      CONSUMED = :consumed

      module_function

      def of(expires_at:, consumed_at:, now:)
        return CONSUMED if consumed_at
        return EXPIRED if now >= expires_at

        USABLE
      end
    end
  end
end
