module Domain
  module Orders
    # Who is buying, as much of them as checkout needs. A guest arrives with no
    # verified email and pays after verifying.
    Purchaser = Data.define(:id, :email, :email_verified_at) do
      def email_verified?
        !email_verified_at.nil?
      end
    end
  end
end
