module Domain
  module Auth
    # A destination reaches the app through a form field and rides on a magic
    # link, so anything that could send the visitor off this site — or split the
    # response header — is dropped rather than carried.
    module LocalRedirect
      CONTROL_CHARACTERS = /[\x00-\x1f\x7f]/

      module_function

      def resolve(requested, fallback:, origin:)
        keep_if_local(requested, origin: origin) || fallback
      end

      # Returns nil when the target does not stay on this site.
      def keep_if_local(requested, origin:)
        target = requested.to_s.strip
        return nil if target.empty? || CONTROL_CHARACTERS.match?(target)
        return target if root_relative?(target)
        return target if target == origin || target.start_with?("#{origin}/")

        nil
      end

      def root_relative?(target)
        target.start_with?("/") && !target.start_with?("//", "/\\")
      end
      private_class_method :root_relative?
    end
  end
end
