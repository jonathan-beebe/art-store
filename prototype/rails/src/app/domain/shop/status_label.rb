module Domain
  module Shop
    module StatusLabel
      module_function

      # Order and fulfillment states are stored snake_case; a page reads one
      # back as a sentence.
      def humanize(status)
        status.to_s.tr("_", " ").capitalize
      end
    end
  end
end
