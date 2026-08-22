module Domain
  module Shop
    # What a storefront visitor asked to see: free text over the catalogue and a
    # medium to narrow it to.
    ListingSearch = Data.define(:term, :medium) do
      def self.from_input(term:, medium:)
        new(term: filled(term), medium: filled(medium))
      end

      def self.filled(value)
        text = value.to_s.strip

        text.empty? ? nil : text
      end
      private_class_method :filled

      def term?
        !term.nil?
      end

      def medium?
        !medium.nil?
      end

      # SQLite LIKE has no escape character unless the query names one, so a
      # wildcard the visitor typed is dropped rather than escaped.
      def like_pattern
        raise ArgumentError, "a search without a term has no pattern" unless term?

        "%#{term.tr('%_', '  ').squeeze(' ').strip}%"
      end
    end
  end
end
