require_relative "../money"

module Domain
  module Listings
    # The listing fields a seller types into the create or edit form, checked
    # and converted. Status, slug, and image are the portal's to decide, so a
    # draft never carries them.
    class ListingDraft < Data.define(:title, :description, :medium, :dimensions, :price, :quantity)
      LINE_LIMIT = 255
      DESCRIPTION_LIMIT = 5_000
      QUANTITY_LIMIT = 999
      DOLLARS = /\A\d+(\.\d{1,2})?\z/
      WHOLE_NUMBER = /\A\d+\z/
      IMAGE_CONTENT_TYPE = %r{\Aimage/}

      # Submitted fields, as the form sends them: strings, plus the content
      # type of the upload when there is one.
      def self.errors_for(fields)
        {
          title: title_error(fields[:title]),
          description: line_error(fields[:description], DESCRIPTION_LIMIT, "description"),
          medium: line_error(fields[:medium], LINE_LIMIT, "medium"),
          dimensions: line_error(fields[:dimensions], LINE_LIMIT, "dimensions"),
          price: price_error(fields[:price]),
          quantity: quantity_error(fields[:quantity]),
          image: image_error(fields[:image_content_type])
        }.compact
      end

      def self.from(fields)
        new(
          title: fields[:title].to_s.strip,
          description: written(fields[:description]),
          medium: written(fields[:medium]),
          dimensions: written(fields[:dimensions]),
          price: Money.from_dollars(fields[:price]),
          quantity: fields[:quantity].to_i
        )
      end

      def self.title_error(value)
        return "Enter a title." if value.to_s.strip.empty?

        line_error(value, LINE_LIMIT, "title")
      end

      def self.line_error(value, limit, field)
        "Keep the #{field} under #{limit} characters." if value.to_s.strip.length > limit
      end

      def self.price_error(value)
        "The price is an amount in dollars, like 249.00." unless DOLLARS.match?(value.to_s.strip)
      end

      def self.quantity_error(value)
        quantity = value.to_s.strip
        return if WHOLE_NUMBER.match?(quantity) && quantity.to_i <= QUANTITY_LIMIT

        "The quantity is a whole number from 0 to #{QUANTITY_LIMIT}."
      end

      def self.image_error(content_type)
        return if content_type.nil? || IMAGE_CONTENT_TYPE.match?(content_type)

        "Upload an image file."
      end

      def self.written(value)
        text = value.to_s.strip

        text.empty? ? nil : text
      end

      private_class_method :title_error, :line_error, :price_error, :quantity_error, :image_error, :written

      def attributes
        {
          title: title,
          description: description,
          medium: medium,
          dimensions: dimensions,
          price_cents: price.cents,
          quantity: quantity
        }
      end
    end
  end
end
