module Domain
  module Auth
    # Addresses arrive from sign-in forms and from magic-link rows; both sides
    # compare the normalized form so one person never ends up with two accounts.
    module EmailAddress
      SHAPE = /\A[^@\s]+@[^@\s]+\.[^@\s]+\z/

      module_function

      def normalize(email)
        email.to_s.strip.downcase
      end

      def valid?(email)
        SHAPE.match?(normalize(email))
      end
    end
  end
end
